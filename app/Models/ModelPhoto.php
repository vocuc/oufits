<?php

namespace App\Models;

use App\Support\CoverImage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/** Một ảnh của người mẫu: ảnh gốc + thumbnail WebP ở models/{user_id}/ trên disk ảnh bìa (riêng tư). */
class ModelPhoto extends Model
{
    protected $table = 'fashion_model_photos';

    protected $guarded = ['id'];

    public function model(): BelongsTo
    {
        return $this->belongsTo(FashionModel::class, 'fashion_model_id');
    }

    public function store(UploadedFile $file): void
    {
        $disk = Outfit::coverDisk();
        $base = 'models/'.$this->model->user_id.'/'.Str::uuid();
        $path = $base.'.'.strtolower($file->getClientOriginalExtension() ?: 'jpg');
        throw_unless($disk->put($path, file_get_contents($file->getRealPath()), ['visibility' => 'private']),
            \RuntimeException::class, 'Không lưu được ảnh.');
        $thumb = "{$base}-thumb.webp";
        try {
            $disk->put($thumb, CoverImage::optimize($file), ['visibility' => 'private', 'ContentType' => 'image/webp']);
        } catch (\Throwable) {
            $thumb = null;
        }
        $this->forceFill(['path' => $path, 'thumb_path' => $thumb])->save();
    }

    public function deleteFiles(): void
    {
        Outfit::coverDisk()->delete(array_filter([$this->path, $this->thumb_path]));
    }

    public function url(bool $thumb = false): string
    {
        return route('models.photo', [
            'model' => $this->fashion_model_id, 'photo' => $this->id, 'thumb' => $thumb ? 1 : null,
            'v' => substr(md5($this->path), 0, 10),
        ]);
    }

    /** Trả file ảnh (S3: chuyển hướng link tạm; disk local: trả thẳng). */
    public function response(bool $thumb = false)
    {
        $path = $thumb && $this->thumb_path ? $this->thumb_path : $this->path;
        $disk = Outfit::coverDisk();
        if ($disk->providesTemporaryUrls()) {
            return redirect()->away($disk->temporaryUrl($path, now()->addHour()))->header('Cache-Control', 'private, max-age=3000');
        }
        abort_unless($disk->exists($path), 404);

        return $disk->response($path, null, ['Cache-Control' => 'private, max-age=31536000, immutable']);
    }
}
