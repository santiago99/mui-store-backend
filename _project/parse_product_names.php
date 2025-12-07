<?php

/**
 * Script to parse computer_store_products_extended.json and return
 * unique sorted array of the first N words of product names.
 *
 * Usage: php parse_product_names.php [number_of_words]
 *   number_of_words: Number of words to extract (must be > 0, default: 3)
 */

// Parse command line argument
$numWords = 3; // default
if (isset($argv[1])) {
    $numWords = (int) $argv[1];
    if ($numWords <= 0) {
        echo "Error: Number of words must be greater than 0\n";
        exit(1);
    }
}

$jsonFile = __DIR__.'/computer_store_products_extended.json';

if (! file_exists($jsonFile)) {
    echo "Error: JSON file not found at {$jsonFile}\n";
    exit(1);
}

$jsonContent = file_get_contents($jsonFile);
$data = json_decode($jsonContent, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    echo 'Error: Failed to parse JSON - '.json_last_error_msg()."\n";
    exit(1);
}

if (! isset($data['products']) || ! is_array($data['products'])) {
    echo "Error: 'products' array not found in JSON\n";
    exit(1);
}

$wordCombinations = [];

foreach ($data['products'] as $product) {
    if (! isset($product['name']) || empty($product['name'])) {
        continue;
    }

    $name = trim($product['name']);
    $words = preg_split('/\s+/', $name);

    if (count($words) >= $numWords) {
        $firstN = implode(' ', array_slice($words, 0, $numWords));
        $wordCombinations[] = $firstN;
    } elseif (count($words) > 0) {
        // If product name has less than N words, use all available words
        $firstN = implode(' ', $words);
        $wordCombinations[] = $firstN;
    }
}

// Get unique values and sort
$uniqueWords = array_unique($wordCombinations);
sort($uniqueWords);

// Output as JSON for easy consumption
echo json_encode($uniqueWords, JSON_PRETTY_PRINT)."\n";

// Also output count for reference
echo "\nTotal unique first-{$numWords}-word combinations: ".count($uniqueWords)."\n";
