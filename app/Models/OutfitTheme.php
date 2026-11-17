<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Chủ đề lô phối đồ: CHỈ để gom nhóm các câu hook (người dùng chốt 2026-11-17). Hashtag tìm outfit và trạng thái duyệt
 * nằm ở từng hook (OutfitThemeHook). Worker chọn ngẫu nhiên 1 chủ đề rồi 1 hook đã duyệt còn đủ outfit trong chủ đề đó.
 */
class OutfitTheme extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['user_id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function hooks(): HasMany
    {
        return $this->hasMany(OutfitThemeHook::class)->orderBy('sort')->orderBy('id');
    }

    /** Dữ liệu trả API (worker); $status: chỉ hook ở trạng thái này. */
    public function toApi(?string $status = null): array
    {
        $hooks = $this->hooks->when($status, fn ($c) => $c->where('status', $status))->values();

        return [
            'id' => $this->id, 'name' => $this->name, 'note' => $this->note, 'sort' => $this->sort,
            'hooks' => $hooks->map->toApi()->all(), 'updated_at' => $this->updated_at,
        ];
    }
}
