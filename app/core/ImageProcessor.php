<?php
defined('KOPII_APP') or exit('Direct access denied.');

/**
 * Re-encodes uploaded photos with GD.
 *
 * Why: phones produce 3–6 MB photos that are painfully slow on mobile data, and they carry
 * EXIF metadata including the GPS position of where the photo was taken. Re-encoding gives us
 * web-sized files, a small thumbnail for cards/grids, and no metadata at all.
 *
 * Browsers display rotated phone photos by reading the EXIF "orientation" tag, which is lost
 * when metadata is stripped, so the rotation is applied to the pixels before saving.
 */
final class ImageProcessor
{
    /** Decoded pixel ceiling, so a single upload cannot exhaust shared-hosting memory. */
    private const MAX_PIXELS = 40000000;

    public static function available(): bool
    {
        return extension_loaded('gd') && function_exists('imagecreatetruecolor');
    }

    public static function supports(string $mime): bool
    {
        if (!self::available()) {
            return false;
        }
        return match ($mime) {
            'image/jpeg' => function_exists('imagecreatefromjpeg') && function_exists('imagejpeg'),
            'image/png'  => function_exists('imagecreatefrompng') && function_exists('imagepng'),
            'image/webp' => function_exists('imagecreatefromwebp') && function_exists('imagewebp'),
            default      => false,
        };
    }

    /**
     * Write a resized, metadata-free copy to $destPath and a smaller one to $thumbPath.
     *
     * @return bool true when the processed files were written; false when GD cannot handle
     *              this format and the caller should store the original file instead.
     * @throws RuntimeException when the image is unreadable or too large to process safely.
     */
    public static function process(
        string $srcPath,
        string $mime,
        string $destPath,
        string $thumbPath,
        int $maxEdge,
        int $thumbEdge
    ): bool {
        if (!self::supports($mime)) {
            return false;
        }

        $info = @getimagesize($srcPath);
        if ($info === false) {
            throw new UserFacingException('Uploaded file is not a valid image.');
        }
        self::assertMemory((int) $info[0], (int) $info[1]);

        $image = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($srcPath),
            'image/png'  => @imagecreatefrompng($srcPath),
            default      => @imagecreatefromwebp($srcPath),
        };
        if ($image === false) {
            throw new UserFacingException('That image could not be read. Please try a different JPG, PNG, or WebP photo.');
        }

        if ($mime === 'image/jpeg') {
            $image = self::applyOrientation($image, self::jpegOrientation($srcPath));
        }

        $main = self::fit($image, $maxEdge, $mime);
        if (!self::write($main, $mime, $destPath, 84)) {
            @unlink($destPath);
            throw new UserFacingException('Could not save uploaded image.');
        }

        // The thumbnail is a best-effort extra: thumb_url() falls back to the full image.
        $thumbDir = dirname($thumbPath);
        if (is_dir($thumbDir) || @mkdir($thumbDir, 0775, true)) {
            $thumb = self::fit($main, $thumbEdge, $mime);
            if (!self::write($thumb, $mime, $thumbPath, 78)) {
                @unlink($thumbPath);
            }
        }

        return true;
    }

    /**
     * Scale down so the longest edge is at most $maxEdge (never scales up).
     */
    private static function fit(GdImage $image, int $maxEdge, string $mime): GdImage
    {
        $width  = imagesx($image);
        $height = imagesy($image);
        $scale  = min(1.0, $maxEdge / max($width, $height));
        if ($scale >= 1.0) {
            return $image;
        }

        $newWidth  = max(1, (int) round($width * $scale));
        $newHeight = max(1, (int) round($height * $scale));
        $resized   = imagecreatetruecolor($newWidth, $newHeight);
        if ($resized === false) {
            throw new UserFacingException('Could not resize the uploaded image.');
        }

        if ($mime !== 'image/jpeg') {
            // Keep transparency for PNG / WebP.
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
            imagefilledrectangle($resized, 0, 0, $newWidth, $newHeight, imagecolorallocatealpha($resized, 0, 0, 0, 127));
        }

        imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        return $resized;
    }

    private static function write(GdImage $image, string $mime, string $path, int $quality): bool
    {
        if ($mime === 'image/jpeg') {
            imageinterlace($image, true); // progressive JPEG: shows a blurry preview early on slow links
            return @imagejpeg($image, $path, $quality);
        }

        imagesavealpha($image, true);
        if ($mime === 'image/png') {
            return @imagepng($image, $path, 8);
        }
        return @imagewebp($image, $path, $quality);
    }

    // ---------------------------------------------------------------------------------------
    // EXIF orientation
    // ---------------------------------------------------------------------------------------

    private static function applyOrientation(GdImage $image, int $orientation): GdImage
    {
        if ($orientation <= 1 || !function_exists('imageflip') || !function_exists('imagerotate')) {
            return $image;
        }

        switch ($orientation) {
            case 2:
                imageflip($image, IMG_FLIP_HORIZONTAL);
                return $image;
            case 3:
                return self::rotated($image, 180);
            case 4:
                imageflip($image, IMG_FLIP_VERTICAL);
                return $image;
            case 5:
                imageflip($image, IMG_FLIP_VERTICAL);
                return self::rotated($image, -90);
            case 6:
                return self::rotated($image, -90);
            case 7:
                imageflip($image, IMG_FLIP_HORIZONTAL);
                return self::rotated($image, -90);
            case 8:
                return self::rotated($image, 90);
            default:
                return $image;
        }
    }

    /** GD rotates counter-clockwise, so a negative angle turns the picture clockwise. */
    private static function rotated(GdImage $image, int $angle): GdImage
    {
        $rotated = imagerotate($image, $angle, 0);
        return $rotated === false ? $image : $rotated;
    }

    /**
     * Read the EXIF orientation (1–8) straight from the JPEG bytes, so this works even when
     * PHP's exif extension is not installed. Returns 1 (upright) when absent or unreadable.
     */
    private static function jpegOrientation(string $path): int
    {
        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            return 1;
        }
        $data = (string) fread($handle, 65536); // EXIF lives in an APP1 segment near the start
        fclose($handle);

        $length = strlen($data);
        if ($length < 4 || $data[0] !== "\xFF" || $data[1] !== "\xD8") {
            return 1;
        }

        $pos = 2;
        while ($pos + 4 <= $length) {
            if ($data[$pos] !== "\xFF") {
                break;
            }
            $marker = ord($data[$pos + 1]);
            if ($marker === 0xFF) {                                   // fill byte
                $pos++;
                continue;
            }
            if ($marker === 0x01 || ($marker >= 0xD0 && $marker <= 0xD8)) { // markers without a length
                $pos += 2;
                continue;
            }
            if ($marker === 0xDA || $marker === 0xD9) {              // start of image data / end of image
                break;
            }

            $segmentLength = (ord($data[$pos + 2]) << 8) | ord($data[$pos + 3]);
            if ($segmentLength < 2) {
                break;
            }
            if ($marker === 0xE1 && substr($data, $pos + 4, 6) === "Exif\0\0") {
                return self::tiffOrientation(substr($data, $pos + 10, $segmentLength - 8));
            }
            $pos += 2 + $segmentLength;
        }

        return 1;
    }

    private static function tiffOrientation(string $tiff): int
    {
        $size = strlen($tiff);
        if ($size < 8) {
            return 1;
        }

        $byteOrder = substr($tiff, 0, 2);
        if ($byteOrder !== 'II' && $byteOrder !== 'MM') {
            return 1;
        }
        $little = $byteOrder === 'II';

        $u16 = static function (string $s, int $o) use ($little): int {
            return $little
                ? (ord($s[$o]) | (ord($s[$o + 1]) << 8))
                : ((ord($s[$o]) << 8) | ord($s[$o + 1]));
        };
        $u32 = static function (string $s, int $o) use ($little): int {
            return $little
                ? (ord($s[$o]) | (ord($s[$o + 1]) << 8) | (ord($s[$o + 2]) << 16) | (ord($s[$o + 3]) << 24))
                : ((ord($s[$o]) << 24) | (ord($s[$o + 1]) << 16) | (ord($s[$o + 2]) << 8) | ord($s[$o + 3]));
        };

        $ifd = $u32($tiff, 4);
        if ($ifd < 8 || $ifd + 2 > $size) {
            return 1;
        }

        $entries = $u16($tiff, $ifd);
        for ($i = 0; $i < $entries; $i++) {
            $entry = $ifd + 2 + $i * 12;
            if ($entry + 12 > $size) {
                break;
            }
            if ($u16($tiff, $entry) === 0x0112) {
                $value = $u16($tiff, $entry + 8);
                return ($value >= 1 && $value <= 8) ? $value : 1;
            }
        }

        return 1;
    }

    // ---------------------------------------------------------------------------------------
    // Memory safety
    // ---------------------------------------------------------------------------------------

    private static function assertMemory(int $width, int $height): void
    {
        $pixels = $width * $height;
        $tooBig = new UserFacingException(
            'That photo is very large (' . round($pixels / 1000000) . ' megapixels). Please resize it below 40 megapixels, or take a lower-resolution photo, and try again.'
        );

        if ($pixels > self::MAX_PIXELS) {
            throw $tooBig;
        }

        $limit = self::memoryLimitBytes();
        if ($limit === 0) {
            return; // unlimited
        }

        // GD keeps ~4 bytes per pixel and we may briefly hold a rotated copy as well.
        $needed = memory_get_usage() + ($pixels * 9);
        if ($needed > $limit && $limit < 536870912) {
            @ini_set('memory_limit', '512M');
            $limit = self::memoryLimitBytes();
        }
        if ($limit !== 0 && $needed > $limit) {
            throw $tooBig;
        }
    }

    /** memory_limit in bytes, or 0 when unlimited. */
    private static function memoryLimitBytes(): int
    {
        $raw = trim((string) ini_get('memory_limit'));
        if ($raw === '' || $raw === '-1') {
            return 0;
        }
        $number = (int) $raw;
        return match (strtolower(substr($raw, -1))) {
            'g'     => $number * 1073741824,
            'm'     => $number * 1048576,
            'k'     => $number * 1024,
            default => $number,
        };
    }
}
