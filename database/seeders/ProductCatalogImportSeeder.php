<?php

namespace Database\Seeders;

use App\Enums\FilterType;
use App\Enums\ProductFieldType;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductClass;
use App\Models\ProductField;
use App\Models\ProductFieldValue;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductCatalogImportSeeder extends Seeder
{
    /**
     * Brands array - to be filled manually
     */
    private array $brands = [
        'AMD',
        'ASRock',
        'ASUS',
        'Acer',
        'Apple',
        'Asus',
        'BenQ',
        'Cooler',
        'Corsair',
        'Crucial',
        'CyberPowerPC',
        'Dell',
        'EVGA',
        'Fractal',
        'G.Skill',
        'Gigabyte',
        'HP',
        'Intel',
        'Kingston',
        'LG',
        'Lenovo',
        'Logitech',
        'MSI',
        'NVIDIA',
        'NZXT',
        'Razer',
        'Samsung',
        'Seagate',
        'Seasonic',
        'SteelSeries',
        'Toshiba',
        'Western Digital',
        'iBUYPOWER',
    ];

    /**
     * Category name hash for fuzzy matching
     */
    private array $categoryMap = [];

    /**
     * Product fields cache
     */
    private array $productFields = [];

    /**
     * Images cache per directory
     */
    private array $imagesCache = [];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $jsonPath = base_path('_project/_local/product_sample_data/computer_store_products_extended.json');

        if (! file_exists($jsonPath)) {
            $this->command->error("JSON file not found: {$jsonPath}");

            return;
        }

        $data = json_decode(file_get_contents($jsonPath), true);

        if (! $data || ! isset($data['categories']) || ! isset($data['products'])) {
            $this->command->error('Invalid JSON structure');

            return;
        }

        // Step 1: Create category tree
        $this->command->info('Creating category tree...');
        $this->createCategoryTree($data['categories']);

        // Step 2: Extract and process product properties
        $this->command->info('Extracting product properties...');
        $propertyData = $this->extractProductProperties($data['products']);

        // Step 3: Create ProductFields
        $this->command->info('Creating product fields...');
        $this->createProductFields($propertyData);

        // Step 4: Create ProductClasses
        $this->command->info('Creating product classes...');
        $this->createProductClasses($data['products']);

        // Step 5: Create Products
        $this->command->info('Creating products...');
        $this->createProducts($data['products']);

        $this->command->info('Seeding completed!');
    }

    /**
     * Recursively create category tree from nested structure
     */
    private function createCategoryTree(array $categories, ?Category $parent = null): void
    {
        foreach ($categories as $name => $children) {
            $category = Category::create([
                'name' => $name,
                'slug' => Str::slug($name),
                'description' => null,
                'is_active' => true,
                'parent_id' => $parent?->id,
            ]);

            // Add to category map for exact matching (case-insensitive key)
            $this->categoryMap[strtolower(trim($name))] = $category;

            // Recursively create children
            if (! empty($children)) {
                $this->createCategoryTree($children, $category);
            }
        }
    }

    /**
     * Extract and analyze all product properties
     */
    private function extractProductProperties(array $products): array
    {
        $propertyData = [];

        foreach ($products as $product) {
            if (! isset($product['properties']) || ! is_array($product['properties'])) {
                continue;
            }

            foreach ($product['properties'] as $propertyName => $value) {
                if (! isset($propertyData[$propertyName])) {
                    $propertyData[$propertyName] = [
                        'values' => [],
                        'suffix' => null,
                        'type' => null,
                    ];
                }

                // Handle array values
                if (is_array($value)) {
                    $value = implode(', ', $value);
                }

                $propertyData[$propertyName]['values'][] = $value;
            }
        }

        // Analyze each property to determine type and extract suffixes
        foreach ($propertyData as $propertyName => &$data) {
            $this->analyzeProperty($propertyName, $data);
        }

        return $propertyData;
    }

    /**
     * Analyze property to determine type and extract suffix
     */
    private function analyzeProperty(string $propertyName, array &$data): void
    {
        // Suffixes to detect (with and without leading space, sorted by length descending)
        $suffixes = ['GB', 'MB/s', 'GHz', 'MHz', 'kg', 'Hz', 'W', '"'];
        $hasNumeric = false;
        $hasFloat = false;
        $hasBoolean = false;
        $hasString = false;
        $detectedSuffix = null;

        foreach ($data['values'] as $value) {
            if (is_bool($value)) {
                $hasBoolean = true;

                continue;
            }

            if (is_array($value)) {
                $value = implode(', ', $value);
            }

            $stringValue = (string) $value;
            $originalValue = $stringValue;

            // Try to extract suffix (check longer suffixes first to avoid partial matches)
            usort($suffixes, fn ($a, $b) => strlen($b) <=> strlen($a));
            foreach ($suffixes as $suffix) {
                if (str_ends_with($stringValue, $suffix)) {
                    $detectedSuffix = $suffix;
                    $stringValue = rtrim(substr($stringValue, 0, -strlen($suffix)));
                    break;
                }
            }

            // Check if numeric
            if (is_numeric($stringValue)) {
                $hasNumeric = true;
                if (str_contains($stringValue, '.')) {
                    $hasFloat = true;
                }
            } else {
                $hasString = true;
            }
        }

        // Determine type
        if ($hasBoolean && ! $hasNumeric && ! $hasString) {
            $data['type'] = ProductFieldType::String; // Store boolean as string
        } elseif ($hasNumeric && ! $hasString) {
            $data['type'] = $hasFloat ? ProductFieldType::Float : ProductFieldType::Integer;
        } else {
            // Check if all values are from a limited set (enum-like)
            $uniqueValues = array_unique(array_filter($data['values'], fn ($v) => ! is_bool($v)));
            if (count($uniqueValues) <= 20 && count($uniqueValues) < count($data['values']) * 0.5) {
                $data['type'] = ProductFieldType::Enum;
            } else {
                $data['type'] = ProductFieldType::String;
            }
        }

        // Store suffix if detected
        if ($detectedSuffix) {
            $data['suffix'] = $detectedSuffix;
        }
    }

    /**
     * Create ProductField records
     */
    private function createProductFields(array $propertyData): void
    {
        foreach ($propertyData as $propertyName => $data) {
            $options = [];
            if ($data['suffix']) {
                $options['suffix'] = $data['suffix'];
            }

            $field = ProductField::create([
                'name' => $propertyName,
                'slug' => Str::slug($propertyName),
                'type' => $data['type'],
                'options' => ! empty($options) ? $options : null,
            ]);

            $this->productFields[$propertyName] = $field;
        }
    }

    /**
     * Create ProductClasses for categories with products
     */
    private function createProductClasses(array $products): void
    {
        // Group products by category
        $productsByCategory = [];
        foreach ($products as $product) {
            $categoryName = $product['category'] ?? null;
            if (! $categoryName) {
                continue;
            }

            if (! isset($productsByCategory[$categoryName])) {
                $productsByCategory[$categoryName] = [];
            }
            $productsByCategory[$categoryName][] = $product;
        }

        // Create ProductClass for each category with products
        foreach ($productsByCategory as $categoryName => $categoryProducts) {
            // Find category using fuzzy matching
            $category = $this->findCategoryByName($categoryName);
            if (! $category) {
                $this->command->warn("Category not found for: {$categoryName}");

                continue;
            }

            // Collect all property names used by products in this category
            $propertyNames = [];
            foreach ($categoryProducts as $product) {
                if (isset($product['properties']) && is_array($product['properties'])) {
                    $propertyNames = array_merge($propertyNames, array_keys($product['properties']));
                }
            }
            $propertyNames = array_unique($propertyNames);

            // Create ProductClass
            $productClass = ProductClass::create([
                'name' => $category->name,
                'slug' => Str::slug($category->name).'-class',
            ]);

            // Associate ProductClass with category
            $category->productClass()->associate($productClass)->save();

            // Attach all ProductFields used by products in this category
            $weight = 0;
            $filterWeight = 0;
            foreach ($propertyNames as $propertyName) {
                if (! isset($this->productFields[$propertyName])) {
                    continue;
                }

                $field = $this->productFields[$propertyName];
                
                // Determine filter type based on field type
                $filterType = match ($field->type) {
                    ProductFieldType::Integer, ProductFieldType::Float => FilterType::Range,
                    ProductFieldType::String, ProductFieldType::Enum => rand(0, 1) === 0 ? FilterType::Checkboxes : FilterType::Select,
                };

                $productClass->fields()->attach($field->id, [
                    'weight' => $weight++,
                    'is_filter' => true,
                    'filter_type' => $filterType->value,
                    'filter_weight' => $filterWeight++,
                    'options' => null,
                ]);
            }
        }
    }

    /**
     * Find category by name using exact matching (case-insensitive)
     */
    private function findCategoryByName(string $name): ?Category
    {
        $normalized = strtolower(trim($name));

        return $this->categoryMap[$normalized] ?? null;
    }

    /**
     * Get random image filename and directory name for a category
     *
     * @return array{filename: string, directory: string}|null
     */
    private function getRandomImageForCategory(string $categoryName): ?array
    {
        $directoryName = $categoryName;
        // Check cache first
        if (! isset($this->imagesCache[$directoryName])) {
            $this->imagesCache[$directoryName] = $this->loadImagesFromDirectory($directoryName);
        }

        $images = $this->imagesCache[$directoryName];
        if (empty($images)) {
            return null;
        }

        return [
            'filename' => $images[array_rand($images)],
            'directory' => $directoryName,
        ];
    }

    /**
     * Load images from directory and cache them
     *
     * @return array<string>
     */
    private function loadImagesFromDirectory(string $directoryName): array
    {
        $imageDir = base_path("_project/_local/sample_images_out/{$directoryName}");
        if (! is_dir($imageDir)) {
            return [];
        }

        // Get all image files
        $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $images = [];
        $files = scandir($imageDir);
        if ($files === false) {
            return [];
        }

        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            if (in_array($extension, $imageExtensions)) {
                $images[] = $file;
            }
        }

        return $images;
    }

    /**
     * Create products from JSON data
     */
    private function createProducts(array $products): void
    {
        foreach ($products as $productData) {
            // Extract brand from product name
            $productName = $productData['name'] ?? '';
            $brandId = null;
            $title = $productName;

            foreach ($this->brands as $brandName) {
                // Case-insensitive check if product name starts with brand name
                if (stripos($productName, $brandName) === 0) {
                    // Brand found at the start - lookup or create brand
                    $brand = Brand::where('name', $brandName)
                        ->orWhere('slug', Str::slug($brandName))
                        ->first();

                    if (! $brand) {
                        // Create brand if it doesn't exist
                        $brand = Brand::create([
                            'name' => $brandName,
                            'slug' => Str::slug($brandName),
                        ]);
                    }

                    $brandId = $brand->id;
                    // Trim brand name from product title (case-insensitive, including space)
                    $title = trim(substr($productName, strlen($brandName)));
                    break;
                }
            }

            // Find category
            $categoryName = $productData['category'] ?? null;
            if (! $categoryName) {
                $this->command->warn("No category for product: {$productName}");

                continue;
            }

            $category = $this->findCategoryByName($categoryName);
            if (! $category || ! $category->productClass) {
                $this->command->warn("Category or ProductClass not found for product: {$productName} [{$categoryName}]");

                continue;
            }

            // Get random image for category
            $imageData = $this->getRandomImageForCategory($categoryName);
            $imageUrl = null;
            if ($imageData) {
                $imageUrl = "/assets/images/products/{$imageData['directory']}/{$imageData['filename']}";
            } else {
                $this->command->warn("No image found for category: [{$categoryName}] (product: {$productName})");
            }

            // Create product
            $price = isset($productData['price']) ? round((float) $productData['price'], -2) : 0;

            $product = Product::create([
                'title' => $title,
                'description' => null,
                'price' => $price,
                'imageUrl' => $imageUrl,
                'category_id' => $category->id,
                'sku' => null,
                'product_class_id' => $category->productClass->id,
                'brand_id' => $brandId,
            ]);

            // Create ProductFieldValues
            if (isset($productData['properties']) && is_array($productData['properties'])) {
                $this->createProductFieldValues($product, $productData['properties']);
            }
        }
    }

    /**
     * Create ProductFieldValue records for a product
     */
    private function createProductFieldValues(Product $product, array $properties): void
    {
        foreach ($properties as $propertyName => $value) {
            if (! isset($this->productFields[$propertyName])) {
                continue;
            }

            $field = $this->productFields[$propertyName];
            $fieldType = $field->type;
            $options = $field->options ?? [];

            // Handle array values
            if (is_array($value)) {
                $value = implode(', ', $value);
            }

            // Handle boolean values
            if (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            }

            // Extract suffix if present
            $suffix = $options['suffix'] ?? null;
            $stringValue = (string) $value;
            $numericValue = $stringValue;

            if ($suffix) {
                // Try exact match first
                if (str_ends_with($stringValue, $suffix)) {
                    $numericValue = rtrim(substr($stringValue, 0, -strlen($suffix)));
                } else {
                    // Try with/without leading space variation
                    $suffixVariations = [
                        $suffix,
                        ltrim($suffix), // without leading space
                        ' '.ltrim($suffix), // with leading space
                    ];
                    foreach ($suffixVariations as $variation) {
                        if ($variation !== $suffix && str_ends_with($stringValue, $variation)) {
                            $numericValue = rtrim(substr($stringValue, 0, -strlen($variation)));
                            break;
                        }
                    }
                }
            }

            // Prepare values based on field type
            $valueString = null;
            $valueInt = null;
            $valueFloat = null;

            match ($fieldType) {
                ProductFieldType::Integer => $valueInt = is_numeric($numericValue) ? (int) $numericValue : null,
                ProductFieldType::Float => $valueFloat = is_numeric($numericValue) ? (float) $numericValue : null,
                ProductFieldType::String, ProductFieldType::Enum => $valueString = $stringValue,
            };

            ProductFieldValue::create([
                'product_id' => $product->id,
                'product_field_id' => $field->id,
                'value_string' => $valueString,
                'value_int' => $valueInt,
                'value_float' => $valueFloat,
            ]);
        }
    }
}
