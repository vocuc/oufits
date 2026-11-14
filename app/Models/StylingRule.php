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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected static function booted(): void
    {
        static::saving(fn (StylingRule $rule) => $rule->shoe_key = (string) ($rule->shoe_type ?? ''));
    }
}
