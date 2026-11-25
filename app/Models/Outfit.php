<?php

namespace App\Models;

use App\Support\CoverImage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
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

    /** Chủ outfit + tài khoản được chủ chia sẻ xem được mọi outfit; người khác chỉ xem được outfit công khai. */
    public function isVisibleTo(?User $user): bool
    {
        return $this->is_public || ($user && $user->canViewOutfitsOf((int) $this->user_id));
    }

    /** Outfit $user xem được: của mình + của người chia sẻ cho mình + công khai. */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->where(fn (Builder $q) => $q->where('user_id', $user->id)
            ->orWhere('is_public', true)
            ->orWhereIn('user_id', $user->sharedOwners()->select('users.id')));
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

    /** Ảnh gốc lô phối đồ tạo từ outfit này (Thư viện ảnh). */
    public function renders(): HasMany
    {
        return $this->hasMany(OutfitRender::class);
    }

    /** Ảnh tạo gần nhất của outfit: trang danh sách outfit dùng làm ảnh bìa (2026-11-15). */
    public function latestRender(): HasOne
    {
        return $this->hasOne(OutfitRender::class)->latestOfMany();
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

    /**
     * Bỏ outfit vi phạm luật cấm phối đã duyệt của $ruleOwnerId (StylingRule::forbiddenPairs, 2026-11-25):
     *  - outfit TỰ mâu thuẫn: có hashtag giày X và hashtag món bị cấm với X (vd depquai + aomangto);
     *  - $shoes (loại giày sẽ vẽ thay, lô theo sản phẩm): bỏ thêm outfit có món bị cấm với các giày này.
     *
     * @param  string[]  $shoes
     */
    public function scopeWithoutStylingConflicts(Builder $query, int $ruleOwnerId, array $shoes = []): Builder
    {
        $pairs = StylingRule::forbiddenPairs($ruleOwnerId);
        $shoes = array_values(array_filter(array_map(fn ($s) => Hashtag::normalize((string) $s), $shoes)));
        $has = fn (string $tag) => fn (Builder $q) => $q->where('name', $tag);
        foreach ($pairs as [$shoe, $tag]) {
            if (in_array($shoe, $shoes, true)) {
                $query->whereDoesntHave('hashtags', $has($tag));
            } else {
                $query->whereNot(fn (Builder $q) => $q->whereHas('hashtags', $has($shoe))->whereHas('hashtags', $has($tag)));
            }
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
