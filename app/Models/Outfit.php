<?php

namespace App\Models;

use App\Support\CoverImage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\UploadedFile;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Outfit extends Model
{
    protected $fillable = [
        'user_id', 'title', 'style',
        'source_title', 'source_author', 'source_url', 'source_position',
        'items', 'is_public',
    ];

    protected $attributes = [
        'is_public' => true,
    ];

    protected function casts(): array
    {
        return ['is_public' => 'boolean'];
    }

    /** Chủ outfit xem được mọi outfit của mình; người khác chỉ xem được outfit công khai. */
    public function isVisibleTo(?User $user): bool
    {
        return $this->is_public || ($user && $this->user_id === $user->id);
    }

    public function isOwnedBy(?User $user): bool
    {
        return $user !== null && $this->user_id === $user->id;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function hashtags(): BelongsToMany
    {
        return $this->belongsToMany(Hashtag::class)->orderBy('name');
    }

    /** Các món đã tách từ items (cột "items" là văn bản nên quan hệ đặt tên itemsList). */
    public function itemsList(): HasMany
    {
        return $this->hasMany(OutfitItem::class)->orderBy('position');
    }

    /** Outfit phải có ĐỦ tất cả hashtag được chọn. */
    public function scopeWithAllHashtags(Builder $query, array $tags): Builder
    {
        foreach ($tags as $tag) {
            $query->whereHas('hashtags', fn (Builder $q) => $q->where('name', $tag));
        }

        return $query;
    }

    /** Nhận chuỗi "thudong, #vay aokhoac" -> gắn hashtag (tạo mới nếu chưa có). */
    public function syncHashtags(string|array $input): void
    {
        $names = collect(is_array($input) ? $input : preg_split('/[\s,]+/', $input))
            ->map(fn ($t) => Hashtag::normalize((string) $t))
            ->filter()
            ->unique();

        $ids = $names->map(fn ($name) => Hashtag::firstOrCreate(['name' => $name])->id);

        $this->hashtags()->sync($ids);
        Hashtag::pruneUnused();
    }

    /** Disk lưu ảnh bìa (mặc định S3, đổi bằng COVER_DISK). */
    public static function coverDisk(): FilesystemAdapter
    {
        return Storage::disk(config('filesystems.cover_disk'));
    }

    /** Thu nhỏ (tối đa 600px, WebP) rồi lưu ảnh bìa vào ootd/{user_id}/ (xóa ảnh cũ nếu có). */
    public function replaceCover(UploadedFile $file): void
    {
        $old = $this->cover_path;
        $path = 'ootd/'.($this->user_id ?? 0).'/'.Str::uuid().'.webp';
        $saved = static::coverDisk()->put($path, CoverImage::optimize($file), [
            'visibility' => 'private',
            'ContentType' => 'image/webp',
        ]);
        throw_unless($saved, \RuntimeException::class, 'Không lưu được ảnh bìa.');

        $this->cover_path = $path;
        $this->save();

        if ($old) {
            static::coverDisk()->delete($old);
        }
    }

    public function removeCover(): void
    {
        if ($this->cover_path) {
            static::coverDisk()->delete($this->cover_path);
            $this->forceFill(['cover_path' => null])->save();
        }
    }

    /** Link xem ảnh bìa (có ?v= để trình duyệt tải lại khi đổi ảnh), null nếu chưa có. */
    public function coverUrl(): ?string
    {
        return $this->cover_path
            ? route('outfits.cover', ['outfit' => $this, 'v' => substr(md5($this->cover_path), 0, 8)])
            : null;
    }

    protected static function booted(): void
    {
        static::deleted(function (Outfit $outfit) {
            if ($outfit->cover_path) {
                static::coverDisk()->delete($outfit->cover_path);
            }
            Hashtag::pruneUnused();
        });
    }
}
