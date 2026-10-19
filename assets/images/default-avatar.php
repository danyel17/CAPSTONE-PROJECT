<?php
// Create default avatar image
$width = 100;
$height = 100;

// Create image
$image = imagecreatetruecolor($width, $height);

// Define colors
$background = imagecolorallocate($image, 240, 240, 240);
$text_color = imagecolorallocate($image, 100, 100, 100);

// Fill background
imagefill($image, 0, 0, $background);

// Add text
$text = "A";
$font_size = 40;
$font_path = "arial.ttf"; // You might need to adjust this path

// Calculate text position to center it
$bbox = imagettfbbox($font_size, 0, $font_path, $text);
$x = ($width - $bbox[4]) / 2;
$y = ($height - $bbox[5]) / 2;

// Add text to image
imagettftext($image, $font_size, 0, $x, $y, $text_color, $font_path, $text);

// Set content type
header('Content-Type: image/png');

// Output image
imagepng($image);

// Clean up
imagedestroy($image);
?>


