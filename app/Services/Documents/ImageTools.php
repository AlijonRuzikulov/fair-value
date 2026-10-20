<?php

namespace App\Services\Documents;

use App\Models\Attachment;
use GdImage;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Turning attached pictures (photos and scans taken sideways) and making
 * small copies for the lists. The orientation a phone writes into a photo
 * is applied first, so the picture is turned from what people see.
 */
class ImageTools
{
    /** Turns the picture by 90° to the right (clockwise) or to the left and saves it in place. */
    public function rotate(Attachment $file, string $direction): void
    {
        $disk = Storage::disk('local');
        $path = $disk->path($file->path);
        $image = $this->open($path, (string) $file->mime);
        $turned = imagerotate($image, $direction === 'left' ? 90 : -90, 0);
        if (! $turned) {
            throw new RuntimeException('The picture could not be turned.');
        }

        $this->save($turned, $path, (string) $file->mime);
        clearstatcache(true, $path);
        $file->forceFill(['size' => filesize($path)])->save();
        $file->touch();
        $disk->delete($disk->files('thumbs/'.$file->id));
    }

    /** A small JPEG copy, made once and kept until the picture changes. */
    public function thumbnail(Attachment $file, int $size = 360): string
    {
        $disk = Storage::disk('local');
        $thumb = 'thumbs/'.$file->id.'/'.$size.'-'.$file->updated_at?->timestamp.'.jpg';
        if ($disk->exists($thumb)) {
            return $thumb;
        }

        $image = $this->open($disk->path($file->path), (string) $file->mime);
        $scale = min(1, $size / max(imagesx($image), imagesy($image)));
        $small = imagescale($image, max(1, (int) round(imagesx($image) * $scale)), max(1, (int) round(imagesy($image) * $scale)));
        $disk->delete($disk->files('thumbs/'.$file->id));
        $disk->makeDirectory('thumbs/'.$file->id);
        // A white background for transparent PNGs.
        $canvas = imagecreatetruecolor(imagesx($small), imagesy($small));
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
        imagecopy($canvas, $small, 0, 0, 0, 0, imagesx($small), imagesy($small));
        imagejpeg($canvas, $disk->path($thumb), 82);

        return $thumb;
    }

    private function open(string $path, string $mime): GdImage
    {
        $image = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($path),
            'image/png' => @imagecreatefrompng($path),
            'image/webp' => @imagecreatefromwebp($path),
            default => false,
        };
        if (! $image) {
            throw new RuntimeException('The picture could not be read.');
        }
        if ($mime === 'image/png' || $mime === 'image/webp') {
            imagealphablending($image, false);
            imagesavealpha($image, true);
        }

        return $mime === 'image/jpeg' ? $this->upright($image, $path) : $image;
    }

    /** Applies the EXIF orientation of a photo (the file is saved without it afterwards). */
    private function upright(GdImage $image, string $path): GdImage
    {
        $exif = function_exists('exif_read_data') ? @exif_read_data($path) : false;
        $orientation = (int) ($exif['Orientation'] ?? 1);

        if (in_array($orientation, [2, 4, 5, 7], true)) {
            imageflip($image, $orientation === 4 ? IMG_FLIP_VERTICAL : IMG_FLIP_HORIZONTAL);
        }
        $angle = match ($orientation) {
            3, 4 => 180,
            5, 6 => -90,
            7, 8 => 90,
            default => 0,
        };

        return $angle ? (imagerotate($image, $angle, 0) ?: $image) : $image;
    }

    private function save(GdImage $image, string $path, string $mime): void
    {
        if ($mime !== 'image/jpeg') {
            imagesavealpha($image, true);
        }
        $ok = match ($mime) {
            'image/jpeg' => imagejpeg($image, $path, 90),
            'image/png' => imagepng($image, $path),
            'image/webp' => imagewebp($image, $path, 90),
            default => false,
        };
        if (! $ok) {
            throw new RuntimeException('The picture could not be saved.');
        }
    }
}
