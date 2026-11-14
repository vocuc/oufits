<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Một món đồ trong outfit (tách từ dòng "- ..." của items), thuộc tính là slug không dấu. */
class OutfitItem extends Model
{
    public const SLOTS = ['top', 'outer', 'dress', 'bottom', 'socks', 'shoes', 'bag', 'hat', 'jewelry', 'belt', 'scarf', 'hair', 'other'];

    /** Thuộc tính đơn (chuỗi slug). */
    public const ATTRS = ['category', 'color', 'color2', 'pattern', 'material', 'fit', 'length', 'neckline', 'sleeve'];

    protected $guarded = ['id'];

    protected $hidden = ['user_id'];

    protected function casts(): array
    {
        return ['details' => 'array', 'wear' => 'array', 'position' => 'integer'];
    }

    public function outfit(): BelongsTo
    {
        return $this->belongsTo(Outfit::class);
    }
}
