<?php

namespace App\Models;

use App\Support\CoverImage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * Mẫu ảnh bìa lô phối đồ: ảnh mẫu + cách làm (Claude ghi), trạng thái duyệt (chủ shop duyệt trên web)
 * và yêu cầu của chủ shop. Worker phối đồ chỉ làm bìa theo mẫu đã duyệt.
 * OutfitLayout (Mẫu ảnh outfit) kế thừa class này, chỉ đổi bảng / thư mục ảnh / tên route.
 */
class CoverTemplate extends Model
{
    public const PENDING = 'pending';

    public const APPROVED = 'approved';

    public const REJECTED = 'rejected';

    public const STATUSES = [self::PENDING => 'Chờ duyệt', self::APPROVED => 'Đã duyệt', self::REJECTED => 'Bỏ'];

    /** Tiền tố route web (index / update / image) + thư mục ảnh trên disk. */
    public const ROUTE = 'cover-templates';

    protected $table = 'cover_templates';

    protected $guarded = ['id'];

    protected $hidden = ['user_id', 'image_path', 'thumb_path'];

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime', 'request_updated_at' => 'datetime'];
    }

    /** Lưu ảnh mẫu + thumbnail WebP vào {ROUTE}/{user_id}/ (cùng disk ảnh bìa outfit); xoá ảnh cũ. */
    public function replaceImage(UploadedFile $file): void
    {
        $disk = Outfit::coverDisk();
        $old = array_filter([$this->image_path, $this->thumb_path]);
        $base = static::ROUTE.'/'.$this->user_id.'/'.Str::uuid();
        $path = $base.'.'.strtolower($file->getClientOriginalExtension() ?: 'jpg');
        throw_unless($disk->put($path, file_get_contents($file->getRealPath()), ['visibility' => 'private']),
            \RuntimeException::class, 'Không lưu được ảnh.');
        $thumb = "{$base}-thumb.webp";
        try {
            $disk->put($thumb, CoverImage::optimize($file), ['visibility' => 'private', 'ContentType' => 'image/webp']);
        } catch (\Throwable) {
            $thumb = null;
        }
        $this->forceFill(['image_path' => $path, 'thumb_path' => $thumb])->save();
        if ($old) {
            $disk->delete($old);
        }
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function url(bool $thumb = false): ?string
    {
        // v đổi theo file ảnh: ảnh mẫu bị thay (cùng id) thì trình duyệt không dùng lại ảnh / link S3 cũ đã cache
        return $this->image_path ? route(static::ROUTE.'.image', [
            'template' => $this->id, 'thumb' => $thumb ? 1 : null, 'v' => substr(md5($this->image_path), 0, 10),
        ]) : null;
    }

    public function toApi(): array
    {
        return [...$this->toArray(), 'url' => $this->url()];
    }

    protected static function booted(): void
    {
        static::deleted(function (CoverTemplate $t) {
            Outfit::coverDisk()->delete(array_filter([$t->image_path, $t->thumb_path]));
        });
    }
}
