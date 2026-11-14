<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Một quy tắc chủ shop viết: tạo outfit / ảnh bìa / đặt hook. Worker chỉ dùng quy tắc đang bật. */
class Guideline extends Model
{
    public const CATEGORIES = [
        'outfit' => 'Tạo outfit',
        'cover' => 'Ảnh bìa',
        'hook' => 'Đặt hook',
    ];

    protected $guarded = ['id'];

    protected $hidden = ['user_id'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
