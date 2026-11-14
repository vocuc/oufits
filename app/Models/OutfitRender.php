<?php

namespace App\Models;

use App\Support\CoverImage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/** Ảnh gốc ChatGPT tạo từ một outfit cho lô phối đồ A51 (chưa in hook). */
class OutfitRender extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['user_id', 'image_path', 'thumb_path'];

    public function outfit(): BelongsTo
    {
        return $this->belongsTo(Outfit::class);
    }

    /** Lưu ảnh gốc + thumbnail (WebP 600px) vào renders/{user_id}/ trên cùng disk với ảnh bìa outfit. */
    public static function storeImage(int $userId, UploadedFile $file): array
    {
        $disk = Outfit::coverDisk();
        $base = 'renders/'.$userId.'/'.Str::uuid();
        $ext = strtolower($file->getClientOriginalExtension() ?: 'jpg');
        $path = "{$base}.{$ext}";
        throw_unless($disk->put($path, file_get_contents($file->getRealPath()), ['visibility' => 'private']),
            \RuntimeException::class, 'Không lưu được ảnh.');
        $thumb = "{$base}-thumb.webp";
        try {
            $disk->put($thumb, CoverImage::optimize($file), ['visibility' => 'private', 'ContentType' => 'image/webp']);
        } catch (\Throwable) {
            $thumb = null;
        }

        return [$path, $thumb];
    }

    public function url(bool $thumb = false): string
    {
        return route('renders.image', ['render' => $this, 'thumb' => $thumb ? 1 : null]);
    }

    protected static function booted(): void
    {
        static::deleted(function (OutfitRender $render) {
            Outfit::coverDisk()->delete(array_filter([$render->image_path, $render->thumb_path]));
        });
    }
}
