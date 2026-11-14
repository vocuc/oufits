<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Một ý tưởng phối đồ học từ ảnh, kèm cách thực hiện. */
class StylingIdea extends Model
{
    public const SOURCES = ['tiktok', 'ootd', 'user', 'claude'];

    /** Các cột danh sách slug (lọc bằng ?<cột>=a,b -> có ít nhất một giá trị). */
    public const LIST_FIELDS = ['shoe_types', 'occasions', 'seasons', 'styles', 'colors', 'tags'];

    protected $guarded = ['id'];

    protected $hidden = ['user_id'];

    protected function casts(): array
    {
        return [
            'shoe_types' => 'array',
            'occasions' => 'array',
            'seasons' => 'array',
            'styles' => 'array',
            'colors' => 'array',
            'tags' => 'array',
            'outfit_id' => 'integer',
            'rating' => 'integer',
            'is_confirmed' => 'boolean',
            'times_used' => 'integer',
            'last_used_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function outfit(): BelongsTo
    {
        return $this->belongsTo(Outfit::class);
    }
}
