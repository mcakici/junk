<?php
class uploadimage {

    protected $image;
    protected $image_filename;
    protected $image_type; //IMAGETYPE_PNG
    protected $image_size;
    protected $maxFileSize = 15000000; // approx 15 mb

    function getfileSize() {
        return filesize($this->image_filename);
    }

    function load($filename) {
        if (is_array($filename)) {
            $filename = $filename["tmp_name"];
        }

        $this->image_filename = $filename;
        $this->image_size = getimagesize($filename);
        $this->image_type = $this->image_size[2];

        if ($this->getfileSize() > $this->maxFileSize) {
            return 'File size is too much. Allowed max file size is ' . $this->maxSize;
        }

        if ($this->image_type == IMAGETYPE_JPEG) {
            $this->image = imagecreatefromjpeg($filename);
        } elseif ($this->image_type == IMAGETYPE_GIF) {
            $this->image = imagecreatefromgif($filename);
        } elseif ($this->image_type == IMAGETYPE_PNG) {
            $this->image = imagecreatefrompng($filename);
        } elseif ($this->image_type == IMAGETYPE_WEBP) {
            $this->image = imagecreatefromwebp($filename);
        }
    }

    function save($filename, $image_type = IMAGETYPE_JPEG, $compression = 95, $permissions = null) {
        $image_type = $this->image_type;

        if ($image_type == IMAGETYPE_JPEG) {
            imagejpeg($this->image, $filename, $compression);
        } elseif ($image_type == IMAGETYPE_GIF) {
            imagegif($this->image, $filename);
        } elseif ($image_type == IMAGETYPE_PNG) {
            //imagealphablending($this->image, false);
            //imagesavealpha($this->image,true);
            imagepng($this->image, $filename, 9);
        } elseif ($image_type == IMAGETYPE_WEBP) {
            imagewebp($this->image, $filename, $compression);
        }
        @imagedestroy($filename);
        if ($permissions !== null) {
            chmod($filename, $permissions);
        }
    }

    function watermark($watermark_image = null) {
        // Load the stamp and the photo to apply the watermark to
        if ($watermark_image === null) {
            return;
        }

        $stamp = imagecreatefrompng($watermark_image);
        $im = $this->image;

        // Set the margins for the stamp and get the height/width of the stamp image
        $marge_right = 10;
        $marge_bottom = 10;
        $sx = imagesx($stamp);
        $sy = imagesy($stamp);

        // Copy the stamp image onto our photo using the margin offsets and the photo
        // width to calculate positioning of the stamp.
        imagecopy($im, $stamp, imagesx($im) - $sx - $marge_right, imagesy($im) - $sy - $marge_bottom, 0, 0, imagesx($stamp), imagesy($stamp));
        /*
    // Output and free memory
    imagejpg($im, 'photo_stamp.jpg');
    imagedestroy($im);*/
    }

    function output($image_type = IMAGETYPE_JPEG) {
        if ($image_type == IMAGETYPE_JPEG) {
            imagejpeg($this->image);
        } elseif ($image_type == IMAGETYPE_GIF) {
            imagegif($this->image);
        } elseif ($image_type == IMAGETYPE_PNG) {
            imagepng($this->image);
        } elseif ($image_type == IMAGETYPE_WEBP) {
            imagewebp($this->image);
        }
    }

    function getWidth() {
        return imagesx($this->image);
    }

    function getHeight() {
        return imagesy($this->image);
    }

    function setMAX($width = null, $height = null) {
        if ($this->getWidth() > $width) {
            $this->resizeToWidth($width, null);
        } else if ($this->getHeight() > $height) {
            $this->resizeToHeight($height);
        }
    }

    function resizeToHeight($height) {
        $ratio = $height / $this->getHeight();
        $width = round($this->getWidth() * $ratio);
        $this->resize($width, $height);
    }
    function resizeToWidth($width, $height = null) {
        $maxW = $width;
        $maxH = $height;
        $width_orig = $this->getWidth();
        $height_orig = $this->getHeight();

        if ($maxH == null) {
            if ($width_orig < $maxW) {
                $fwidth = $width_orig;
            } else {
                $fwidth = $maxW;
            }
            $ratio_orig = $width_orig / $height_orig;
            $fheight = $fwidth / $ratio_orig;
        } else {
            if ($width_orig <= $maxW && $height_orig <= $height) {
                $fheight = $height_orig;
                $fwidth = $width_orig;
            } else {
                if ($width_orig > $maxW) {
                    $ratio = ($width_orig / $maxW);
                    $fwidth = $maxW;
                    $fheight = ($height_orig / $ratio);
                    if ($fheight > $maxH) {
                        $ratio = ($fheight / $maxH);
                        $fheight = $maxH;
                        $fwidth = ($fwidth / $ratio);
                    }
                }
                if ($height_orig > $maxH) {
                    $ratio = ($height_orig / $maxH);
                    $fheight = $maxH;
                    $fwidth = ($width_orig / $ratio);
                    if ($fwidth > $maxW) {
                        $ratio = ($fwidth / $maxW);
                        $fwidth = $maxW;
                        $fheight = ($fheight / $ratio);
                    }
                }
            }
        }
        $this->resize($fwidth, $fheight);
    }
    function scale($scale) {
        $width = $this->getWidth() * $scale / 100;
        $height = $this->getheight() * $scale / 100;
        $this->resize($width, $height);
    }

    function square($size) {
        $new_image = imagecreatetruecolor($size, $size);
        $white = imagecolorallocate($new_image, 255, 255, 255);
        imagefill($new_image, 0, 0, $white);

        if ($this->getWidth() > $this->getHeight()) {
            $this->resizeToHeight($size);

            //imagecolortransparent($new_image, imagecolorallocate($new_image, 255, 255, 255));
            //imagealphablending($new_image, false);
            //imagesavealpha($new_image, true);
            imagecopy($new_image, $this->image, 0, 0, ($this->getWidth() - $size) / 2, 0, $size, $size);
        } else {
            $this->resizeToWidth($size);

            //imagecolortransparent($new_image, imagecolorallocate($new_image, 255, 255, 255));
            //imagealphablending($new_image, false);
            //imagesavealpha($new_image, true);
            imagecopy($new_image, $this->image, 0, 0, 0, ($this->getHeight() - $size) / 2, $size, $size);
        }
        $this->image = $new_image;
    }

    function rectangle($width, $height) {
        $orjHeight = $height;
        $orjWidth = $width;
        $new_image = imagecreatetruecolor($width, $height);
        if ($this->getWidth() > $this->getHeight()) {

            $ratio = $height / $this->getHeight();
            $width = round($this->getWidth() * $ratio);
            $this->resize($width, $height);

            //$ratio2 = $height / $this->getHeight();
            //$width = round($this->getWidth() * $ratio2);

            //$this->resizeToHeight($height);
            //$top_offset = round(($blank_height - $fheight)/2);
            $left_offset = round(($orjWidth - $width) / 2);
            imagecolortransparent($new_image, imagecolorallocate($new_image, 0, 0, 0));
            imagealphablending($new_image, false);
            imagesavealpha($new_image, true);
            imagecopy($new_image, $this->image, $left_offset, 0, ($this->getWidth() - $width) / 2, ($this->getHeight() - $orjHeight) / 2, $width, $height);
        } else {
            $ratio = $height / $this->getHeight();
            $width = round($this->getWidth() * $ratio);
            //$ratio2 = $height / $this->getHeight();
            //$width = round($this->getWidth() * $ratio2);
            $this->resize($width, $height);
            //yeni fix tam ortala
            $left_offset = ($orjWidth - $width);
            //$this->resizeToWidth($size,$size2);

            imagecolortransparent($new_image, imagecolorallocate($new_image, 0, 0, 0));
            imagealphablending($new_image, false);
            imagesavealpha($new_image, true);
            imagecopy($new_image, $this->image, $left_offset, 0, ($this->getWidth() - $width) / 2, ($this->getHeight() - $orjHeight) / 2, $width, $height);
        }
        $this->image = $new_image;
    }

    function rectangle2($width, $height) {
        $orjHeight = $height;
        $orjWidth = $width;
        $new_image = imagecreatetruecolor($width, $height);
        if ($this->getWidth() > $this->getHeight()) {

            $ratio = $width / $this->getWidth();
            $height = round($this->getHeight() * $ratio);
            $this->resize($width, $height);
            //$ratio2 = $height / $this->getHeight();
            //$width = round($this->getWidth() * $ratio2);
            //$this->resizeToHeight($height);

            imagecolortransparent($new_image, imagecolorallocate($new_image, 0, 0, 0));
            imagealphablending($new_image, false);
            imagesavealpha($new_image, true);
            imagecopy($new_image, $this->image, 0, 0, ($this->getWidth() - $width) / 2, ($this->getHeight() - $orjHeight) / 2, $width, $height);
        } else {
            $ratio = $width / $this->getWidth();
            $height = round($this->getHeight() * $ratio);
            //$ratio2 = $height / $this->getHeight();
            //$width = round($this->getWidth() * $ratio2);
            $this->resize($width, $height);

            //$this->resizeToWidth($size,$size2);

            imagecolortransparent($new_image, imagecolorallocate($new_image, 0, 0, 0));
            imagealphablending($new_image, false);
            imagesavealpha($new_image, true);
            imagecopy($new_image, $this->image, 0, 0, ($this->getWidth() - $width) / 2, ($this->getHeight() - $orjHeight) / 2, $width, $height);
        }
        $this->image = $new_image;
    }

    function resize($width, $height) {
        $new_image = imagecreatetruecolor($width, $height);

        //imagecolortransparent($new_image, imagecolorallocate($new_image, 0, 0, 0));
        //imagealphablending($new_image, false);
        //imagesavealpha($new_image, true);

        imagecopyresampled($new_image, $this->image, 0, 0, 0, 0, $width, $height, $this->getWidth(), $this->getHeight());
        $this->image = $new_image;
    }

}
?>