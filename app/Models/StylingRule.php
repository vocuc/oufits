<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Một nhận định phối đồ: "<loại giày> + <khía cạnh> = <giá trị> thì hợp / rất hợp / nên tránh". */
class StylingRule extends Model
{
    public const DIMENSIONS = ['occasion', 'season', 'style', 'bottom', 'outer', 'color', 'socks', 'age', 'taste', 'pose', 'other'];

    public const RELATIONS = ['good', 'best', 'avoid'];

    public const SOURCES = ['ootd_stats', 'tiktok', 'user', 'claude'];

    protected $guarded = ['id'];

    protected $hidden = ['shoe_key', 'user_id'];

    protected function casts(): array
    {
        return [
            'score' => 'float',
            'evidence_count' => 'integer',
            'examples' => 'array',
            'is_confirmed' => 'boolean',
        ];
    }

    /** Khía cạnh mà value là hashtag món đồ / màu / phong cách (so được với hashtag outfit). "other" là luật viết bằng chữ. */
    public const HASHTAG_DIMENSIONS = ['style', 'bottom', 'outer', 'color', 'socks', 'season'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Luật cấm phối máy đọc được (người dùng chốt 2026-11-25): luật "tránh" ĐÃ DUYỆT, có loại giày, khía cạnh là hashtag.
     * Mỗi cặp [giày, hashtag]: outfit có hashtag giày đó thì không được có hashtag kia (API outfit tự loại).
     *
     * @return array<int, array{0: string, 1: string}>
     */
    public static function forbiddenPairs(int $userId): array
    {
        return static::query()->where('user_id', $userId)->where('relation', 'avoid')->where('is_confirmed', true)
            ->whereNotNull('shoe_type')->whereIn('dimension', self::HASHTAG_DIMENSIONS)
            ->get(['shoe_type', 'value'])
            ->map(fn (StylingRule $r) => [Hashtag::normalize($r->shoe_type), Hashtag::normalize($r->value)])
            ->filter(fn (array $p) => $p[0] !== '' && $p[1] !== '')
            ->unique(fn (array $p) => $p[0].'|'.$p[1])->values()->all();
    }

    protected static function booted(): void
    {
        static::saving(fn (StylingRule $rule) => $rule->shoe_key = (string) ($rule->shoe_type ?? ''));
    }
}
