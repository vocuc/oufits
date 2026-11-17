<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Một câu hook của chủ đề = tiêu đề lô. tags = hashtag outfit phải có ĐỦ (hook nói về mùa thì có tag mùa: thudong / xuanhe).
 * Duyệt riêng từng hook; worker chỉ dùng hook đã duyệt.
 */
class OutfitThemeHook extends Model
{
    public const PENDING = 'pending';

    public const APPROVED = 'approved';

    public const REJECTED = 'rejected';

    public const STATUSES = [
        self::PENDING => 'Chờ duyệt',
        self::APPROVED => 'Đã duyệt',
        self::REJECTED => 'Bỏ',
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime'];
    }

    public function theme(): BelongsTo
    {
        return $this->belongsTo(OutfitTheme::class, 'outfit_theme_id');
    }

    /** "#chanvaymini, TatCaoCo" -> "chanvaymini tatcaoco" (chuẩn hoá như hashtag outfit, bỏ trùng). */
    public static function normalizeTags(string|array|null $input): string
    {
        return collect(is_array($input) ? $input : preg_split('/[\s,]+/u', (string) $input))
            ->map(fn ($t) => Hashtag::normalize((string) $t))
            ->filter()->unique()->implode(' ');
    }

    public static function normalizeBody(?string $body): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', (string) $body));
    }

    /** @return list<string> */
    public function tagList(): array
    {
        return array_values(array_filter(explode(' ', (string) $this->tags)));
    }

    /** Số outfit của chủ shop có ĐỦ hashtag của hook và có ảnh gốc (worker chỉ dùng outfit có ảnh gốc). */
    public function outfitCount(): int
    {
        $tags = $this->tagList();
        if (! $tags) {
            return 0;
        }

        return Outfit::query()->where('user_id', $this->theme->user_id)->whereNotNull('cover_path')
            ->withAllHashtags($tags)->count();
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function toApi(): array
    {
        return [
            'id' => $this->id, 'body' => $this->body, 'tags' => $this->tagList(), 'status' => $this->status,
            'sort' => $this->sort, 'outfit_count' => $this->outfitCount(),
        ];
    }
}
