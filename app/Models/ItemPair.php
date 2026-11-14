<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Độ hợp của một cặp (a, b) ở một cấp so sánh; score -1..+1 (dương = đi cùng nhiều hơn ngẫu nhiên). */
class ItemPair extends Model
{
    public const LEVELS = ['cat', 'cat_color', 'color', 'material', 'pattern', 'style_full', 'cat_shoe', 'color_shoe', 'cat_ctx'];

    protected $guarded = ['id'];

    protected $hidden = ['user_id'];

    protected function casts(): array
    {
        return [
            'count' => 'integer',
            'expected' => 'float',
            'lift' => 'float',
            'score' => 'float',
            'examples' => 'array',
        ];
    }
}
