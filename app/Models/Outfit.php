<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Outfit extends Model
{
    protected $fillable = [
        'user_id', 'title', 'style',
        'source_title', 'source_author', 'source_url', 'source_position',
        'items',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function hashtags(): BelongsToMany
    {
        return $this->belongsToMany(Hashtag::class)->orderBy('name');
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

    /** Lưu ảnh bìa mới (xóa ảnh cũ nếu có). */
    public function replaceCover(UploadedFile $file): void
    {
        $old = $this->cover_path;
        $this->cover_path = $file->storeAs('covers', Str::uuid().'.'.$file->extension(), 'local');
        $this->save();

        if ($old) {
            Storage::disk('local')->delete($old);
        }
    }

    public function removeCover(): void
    {
        if ($this->cover_path) {
            Storage::disk('local')->delete($this->cover_path);
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
                Storage::disk('local')->delete($outfit->cover_path);
            }
            Hashtag::pruneUnused();
        });
    }
}
