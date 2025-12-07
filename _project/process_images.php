#!/usr/bin/env php
<?php

/**
 * Image Processing Script
 *
 * Processes images from subdirectories in sample_images_in:
 * - Converts all images to JPEG format
 * - Renames .jpeg to .jpg
 * - Handles transparency by adding white background
 * - Scales down images larger than 1024x768 to fit within those dimensions
 * - Saves processed images to corresponding subdirectories in sample_images_out
 * - Names output files as img1.jpg, img2.jpg, etc. (incremental per subdirectory)
 */

// Configuration
$inputDir = __DIR__.'/_local/sample_images_in';
$outputDir = __DIR__.'/_local/sample_images_out';
$maxWidth = 1024;
$maxHeight = 768;
$jpgQuality = 85;

// Check if GD extension is available
if (! extension_loaded('gd')) {
    fwrite(STDERR, "Error: GD extension is not loaded. Please install php-gd.\n");
    exit(1);
}

// Check if input directory exists
if (! is_dir($inputDir)) {
    fwrite(STDERR, "Error: Input directory does not exist: {$inputDir}\n");
    exit(1);
}

// Create output directory if it doesn't exist
if (! is_dir($outputDir)) {
    if (! mkdir($outputDir, 0755, true)) {
        fwrite(STDERR, "Error: Failed to create output directory: {$outputDir}\n");
        exit(1);
    }
}

// Get all subdirectories from input directory
$subdirs = array_filter(glob($inputDir.'/*'), 'is_dir');

if (empty($subdirs)) {
    fwrite(STDERR, "Warning: No subdirectories found in {$inputDir}\n");
    exit(0);
}

$totalProcessed = 0;
$totalErrors = 0;

// Process each subdirectory
foreach ($subdirs as $subdir) {
    $subdirName = basename($subdir);
    $outputSubdir = $outputDir.'/'.$subdirName;

    // Create output subdirectory if it doesn't exist
    if (! is_dir($outputSubdir)) {
        if (! mkdir($outputSubdir, 0755, true)) {
            fwrite(STDERR, "Warning: Failed to create output subdirectory: {$outputSubdir}\n");

            continue;
        }
    }

    echo "Processing subdirectory: {$subdirName}\n";

    // Get all image files from the subdirectory
    $imageFiles = [];
    $files = scandir($subdir);
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') {
            continue;
        }

        $filePath = $subdir.'/'.$file;
        if (! is_file($filePath)) {
            continue;
        }

        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if (in_array($extension, ['jpg', 'jpeg', 'png', 'avif', 'webp'])) {
            $imageFiles[] = $filePath;
        }
    }

    if (empty($imageFiles)) {
        echo "  No image files found in {$subdirName}\n";

        continue;
    }

    // Process each image file
    $imageIndex = 1;
    foreach ($imageFiles as $imageFile) {
        $result = processImage($imageFile, $outputSubdir, $imageIndex, $maxWidth, $maxHeight, $jpgQuality);
        if ($result) {
            $totalProcessed++;
            $imageIndex++;
        } else {
            $totalErrors++;
        }
    }

    echo '  Processed '.($imageIndex - 1)." images in {$subdirName}\n\n";
}

echo "Done! Successfully processed {$totalProcessed} images";
if ($totalErrors > 0) {
    echo " with {$totalErrors} errors";
}
echo ".\n";

exit($totalErrors > 0 ? 1 : 0);

/**
 * Process a single image file
 *
 * @param  string  $inputFile  Path to input image file
 * @param  string  $outputDir  Output directory path
 * @param  int  $index  Image index for naming (img1.jpg, img2.jpg, etc.)
 * @param  int  $maxWidth  Maximum width for resizing
 * @param  int  $maxHeight  Maximum height for resizing
 * @param  int  $jpgQuality  JPEG quality (0-100)
 * @return bool True on success, false on failure
 */
function processImage(string $inputFile, string $outputDir, int $index, int $maxWidth, int $maxHeight, int $jpgQuality): bool
{
    $extension = strtolower(pathinfo($inputFile, PATHINFO_EXTENSION));
    $outputFile = $outputDir.'/img'.$index.'.jpg';

    // Load image based on format
    $image = null;
    switch ($extension) {
        case 'jpg':
        case 'jpeg':
            $image = @imagecreatefromjpeg($inputFile);
            break;
        case 'png':
            $image = @imagecreatefrompng($inputFile);
            break;
        case 'webp':
            if (function_exists('imagecreatefromwebp')) {
                $image = @imagecreatefromwebp($inputFile);
            } else {
                // Try using imagecreatefromstring as fallback
                $imageData = @file_get_contents($inputFile);
                if ($imageData !== false) {
                    $image = @imagecreatefromstring($imageData);
                }
            }
            break;
        case 'avif':
            if (function_exists('imagecreatefromavif')) {
                $image = @imagecreatefromavif($inputFile);
            } else {
                // Try using imagecreatefromstring as fallback
                $imageData = @file_get_contents($inputFile);
                if ($imageData !== false) {
                    $image = @imagecreatefromstring($imageData);
                }
            }
            break;
    }

    if ($image === false) {
        fwrite(STDERR, "  Error: Failed to load image: {$inputFile}\n");

        return false;
    }

    $originalWidth = imagesx($image);
    $originalHeight = imagesy($image);

    // Calculate new dimensions if resizing is needed
    $newWidth = $originalWidth;
    $newHeight = $originalHeight;
    $needsResize = false;

    if ($originalWidth > $maxWidth || $originalHeight > $maxHeight) {
        $needsResize = true;
        $ratio = min($maxWidth / $originalWidth, $maxHeight / $originalHeight);
        $newWidth = (int) round($originalWidth * $ratio);
        $newHeight = (int) round($originalHeight * $ratio);
    }

    // Create output image
    if ($needsResize) {
        $outputImage = imagecreatetruecolor($newWidth, $newHeight);
    } else {
        $outputImage = imagecreatetruecolor($originalWidth, $originalHeight);
    }

    // Set white background (handles transparency)
    $white = imagecolorallocate($outputImage, 255, 255, 255);
    imagefill($outputImage, 0, 0, $white);

    // Enable alpha blending for proper transparency handling
    imagealphablending($outputImage, true);

    // Copy and resize if needed
    if ($needsResize) {
        imagecopyresampled(
            $outputImage,
            $image,
            0, 0, // Destination x, y
            0, 0, // Source x, y
            $newWidth, $newHeight, // Destination width, height
            $originalWidth, $originalHeight // Source width, height
        );
    } else {
        imagecopy($outputImage, $image, 0, 0, 0, 0, $originalWidth, $originalHeight);
    }

    // Save as JPEG
    $success = imagejpeg($outputImage, $outputFile, $jpgQuality);

    // Free memory
    imagedestroy($image);
    imagedestroy($outputImage);

    if ($success) {
        $sizeInfo = $needsResize
            ? " ({$originalWidth}x{$originalHeight} -> {$newWidth}x{$newHeight})"
            : " ({$originalWidth}x{$originalHeight})";
        echo "  Saved: img{$index}.jpg{$sizeInfo}\n";

        return true;
    } else {
        fwrite(STDERR, "  Error: Failed to save image: {$outputFile}\n");

        return false;
    }
}
