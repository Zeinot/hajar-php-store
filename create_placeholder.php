<?php
// This script creates a simple placeholder image
header('Content-Type: image/jpeg');
 = imagecreatetruecolor(300, 300);
 = imagecolorallocate(, 240, 240, 240);
 = imagecolorallocate(, 100, 100, 100);
imagefill(, 0, 0, );
imagestring(, 5, 100, 140, 'Product Image', );
imagejpeg();
imagedestroy();

