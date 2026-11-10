<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class Hashtag extends Model
{
    protected $fillable = ['name'];

    public function outfits(): BelongsToMany
    {
        return $this->belongsToMany(Outfit::class);
    }

    /** Xóa các hashtag không còn outfit nào dùng. */
    public static function pruneUnused(): void
    {
        static::doesntHave('outfits')->delete();
    }

    /** "Giày cao gót" / "#giay_cao-got" -> "giaycaogot" */
    public static function normalize(string $tag): string
    {
        return preg_replace('/[^a-z0-9]/', '', Str::lower(Str::ascii(str_replace(['đ', 'Đ'], 'd', $tag))));
    }
}
