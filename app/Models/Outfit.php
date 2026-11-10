<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Outfit extends Model
{
    protected $fillable = [
        'title', 'style',
        'source_title', 'source_author', 'source_url', 'source_position',
        'prompt', 'fix_prompt',
    ];

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

    protected static function booted(): void
    {
        static::deleted(fn () => Hashtag::pruneUnused());
    }
}
