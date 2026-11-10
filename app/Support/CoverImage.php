<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use RuntimeException;

/**
 * Thu nhỏ ảnh bìa: cạnh dài nhất tối đa 600px, xoay đúng chiều theo EXIF, lưu WebP.
 */
class CoverImage
{
    public const MAX_SIDE = 600;

    public const QUALITY = 80;

    /** Trả về nội dung file WebP đã tối ưu. */
    public static function optimize(UploadedFile|string $file): string
    {
        $path = $file instanceof UploadedFile ? $file->getRealPath() : $file;
        $src = @imagecreatefromstring((string) file_get_contents($path));
        throw_unless($src, RuntimeException::class, 'Không đọc được ảnh bìa.');

        $src = self::fixOrientation($src, $path);

        $w = imagesx($src);
        $h = imagesy($src);
        $scale = min(1, self::MAX_SIDE / max($w, $h));
        $nw = max(1, (int) round($w * $scale));
        $nh = max(1, (int) round($h * $scale));

        $dst = imagecreatetruecolor($nw, $nh);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);

        ob_start();
        imagewebp($dst, null, self::QUALITY);
        $data = (string) ob_get_clean();

        imagedestroy($src);
        imagedestroy($dst);

        return $data;
    }

    private static function fixOrientation(\GdImage $img, string $path): \GdImage
    {
        if (! function_exists('exif_read_data')) {
            return $img;
        }

        $exif = @exif_read_data($path);
        $angle = match ((int) ($exif['Orientation'] ?? 1)) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };

        return $angle ? (imagerotate($img, $angle, 0) ?: $img) : $img;
    }
}
