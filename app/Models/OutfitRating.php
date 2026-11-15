<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Điểm chủ shop chấm ảnh phối đồ (1-10), lưu bền theo outfit: ảnh bị xoá thì render_id = null nhưng điểm vẫn còn
 * và vẫn được StylingEngine::feedbackRows() học.
 */
class OutfitRating extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['user_id'];

    protected function casts(): array
    {
        return ['rating' => 'integer'];
    }

    public function outfit(): BelongsTo
    {
        return $this->belongsTo(Outfit::class);
    }
}
