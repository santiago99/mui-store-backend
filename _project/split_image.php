#!/usr/bin/env php
<?php

/**
 * Image Grid Splitter Script
 *
 * Splits a grid image into individual pieces:
 * - Removes 30px margin from all sides
 * - Cuts into 145x130px pieces
 * - Saves as JPG files (pic1.jpg, pic2.jpg, etc.)
 * - Processes left-to-right, top-to-bottom
 */

// Configuration
$sourceFile = __DIR__.'/_local/ChatGPT Image Dec 3, 2025, 05_07_50 PM.png';
$outputDir = __DIR__.'/_local/sample_pics';
$marginX = 29;
$marginY = 31;
$cols = 7; // number of images in column
$rows = 11; // number of images in row
// $pieceWidth = 138;
// $pieceHeight = 134;
$jpgQuality = 70;

// Check if GD extension is available
if (! extension_loaded('gd')) {
    fwrite(STDERR, "Error: GD extension is not loaded. Please install php-gd.\n");
    exit(1);
}

// Check if source file exists
if (! file_exists($sourceFile)) {
    fwrite(STDERR, "Error: Source file not found: {$sourceFile}\n");
    exit(1);
}

// Check if output directory exists and is writable
if (! is_dir($outputDir)) {
    fwrite(STDERR, "Error: Output directory does not exist: {$outputDir}\n");
    exit(1);
}

if (! is_writable($outputDir)) {
    fwrite(STDERR, "Error: Output directory is not writable: {$outputDir}\n");
    exit(1);
}

// Load source image
$sourceImage = @imagecreatefrompng($sourceFile);
if ($sourceImage === false) {
    fwrite(STDERR, "Error: Failed to load PNG image: {$sourceFile}\n");
    exit(1);
}

// Get original dimensions
$originalWidth = imagesx($sourceImage);
$originalHeight = imagesy($sourceImage);

echo "Original image size: {$originalWidth}x{$originalHeight}px\n";

// Calculate cropped dimensions (remove margin from all sides)
$croppedWidth = $originalWidth - (2 * $marginX);
$croppedHeight = $originalHeight - (2 * $marginY);

if ($croppedWidth <= 0 || $croppedHeight <= 0) {
    fwrite(STDERR, "Error: Image too small after removing margins. Cropped size would be: {$croppedWidth}x{$croppedHeight}px\n");
    exit(1);
}

echo "After removing {$marginX}px & {$marginY}px margins: {$croppedWidth}x{$croppedHeight}px\n";

// Crop the image to remove margins
$croppedImage = imagecrop($sourceImage, [
    'x' => $marginX,
    'y' => $marginY,
    'width' => $croppedWidth,
    'height' => $croppedHeight,
]);

$sourceImage = null; // free memory

if ($croppedImage === false) {
    fwrite(STDERR, "Error: Failed to crop image.\n");
    exit(1);
}

$pieceWidth = floor($croppedWidth / $cols);
$pieceHeight = floor($croppedHeight / $rows);

// Calculate grid dimensions
echo "Grid dimensions: {$cols} columns x {$rows} rows\n";
echo "Piece size: {$pieceWidth}x{$pieceHeight}px\n";
echo 'Total pieces: '.($cols * $rows)."\n\n";

// if ($cols <= 0 || $rows <= 0) {
//     fwrite(STDERR, "Error: Image too small to extract any pieces.\n");
//     imagedestroy($croppedImage);
//     exit(1);
// }
if ($pieceWidth <= 0 || $pieceHeight <= 0) {
    fwrite(STDERR, "Error: Image too small to extract any pieces.\n");
    exit(1);
}

// Extract and save pieces (left-to-right, top-to-bottom)
$pieceNumber = 1;
$savedCount = 0;

for ($row = 0; $row < $rows; $row++) {
    for ($col = 0; $col < $cols; $col++) {
        // Calculate source position
        $srcX = $col * $pieceWidth;
        $srcY = $row * $pieceHeight;

        // Create new image for this piece
        $pieceImage = imagecreatetruecolor($pieceWidth, $pieceHeight);

        // Preserve transparency for PNG (though we're saving as JPG)
        // Set white background for JPG
        $white = imagecolorallocate($pieceImage, 255, 255, 255);
        imagefill($pieceImage, 0, 0, $white);

        // Copy the piece from cropped image
        imagecopy(
            $pieceImage,
            $croppedImage,
            0, 0, // Destination x, y
            $srcX, $srcY, // Source x, y
            $pieceWidth, $pieceHeight // Width, height
        );

        // Generate output filename
        $outputFile = $outputDir.'/pic'.str_pad($pieceNumber, 2, '0', STR_PAD_LEFT).'.jpg';

        // Save as JPG
        if (imagejpeg($pieceImage, $outputFile, $jpgQuality)) {
            $savedCount++;
            echo "Saved: pic{$pieceNumber}.jpg\n";
        } else {
            fwrite(STDERR, "Warning: Failed to save piece {$pieceNumber} to {$outputFile}\n");
        }

        $pieceNumber++;
    }
}

echo "\nDone! Successfully saved {$savedCount} pieces.\n";
exit(0);
