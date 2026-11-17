<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Chủ đề lô phối đồ: hashtag bắt buộc (outfit phải có đủ) + nhiều câu hook (tiêu đề lô) + mùa.
 * Worker chọn ngẫu nhiên trong các chủ đề ĐÃ DUYỆT còn đủ outfit; chủ shop thêm / sửa / duyệt trên trang Chủ đề.
 */
class OutfitTheme extends Model
{
    public const PENDING = 'pending';

    public const APPROVED = 'approved';

    public const REJECTED = 'rejected';

    public const STATUSES = [
        self::PENDING => 'Chờ duyệt',
        self::APPROVED => 'Đã duyệt',
        self::REJECTED => 'Bỏ',
    ];

    public const SEASONS = [
        'thudong' => 'Thu đông',
        'xuanhe' => 'Xuân hè',
    ];

    protected $guarded = ['id'];

    protected $hidden = ['user_id'];

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** "#chanvaymini, Tất cao cổ" -> "chanvaymini tatcaoco" (chuẩn hoá như hashtag outfit, bỏ trùng). */
    public static function normalizeTags(string|array|null $input): string
    {
        return collect(is_array($input) ? $input : preg_split('/[\s,]+/u', (string) $input))
            ->map(fn ($t) => Hashtag::normalize((string) $t))
            ->filter()->unique()->implode(' ');
    }

    /** Mỗi dòng một hook: bỏ dòng trống, khoảng trắng thừa, dòng trùng. */
    public static function normalizeHooks(string|array|null $input): string
    {
        return collect(is_array($input) ? $input : preg_split('/\R/u', (string) $input))
            ->map(fn ($h) => trim(preg_replace('/\s+/u', ' ', (string) $h)))
            ->filter()->unique()->implode("\n");
    }

    /** @return list<string> */
    public function tagList(): array
    {
        return array_values(array_filter(explode(' ', (string) $this->tags)));
    }

    /** @return list<string> */
    public function hookList(): array
    {
        // /u bắt buộc: thiếu nó \R khớp byte 0x85 nằm trong chữ "ễ" (E1 BB 85) -> cắt giữa chữ, UTF-8 hỏng
        return array_values(array_filter(preg_split('/\R/u', (string) $this->hooks)));
    }

    /** Số outfit của chủ shop có ĐỦ hashtag của chủ đề và có ảnh gốc (worker chỉ dùng outfit có ảnh gốc). */
    public function outfitCount(): int
    {
        $tags = $this->tagList();
        if (! $tags) {
            return 0;
        }

        return Outfit::query()->where('user_id', $this->user_id)->whereNotNull('cover_path')
            ->withAllHashtags($tags)->count();
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function seasonLabel(): string
    {
        return self::SEASONS[$this->season] ?? 'Mọi mùa';
    }

    /** Dữ liệu trả API (worker): tags / hooks dạng mảng + số outfit khớp. */
    public function toApi(): array
    {
        return [
            'id' => $this->id, 'name' => $this->name, 'tags' => $this->tagList(), 'hooks' => $this->hookList(),
            'season' => $this->season, 'note' => $this->note, 'status' => $this->status, 'sort' => $this->sort,
            'outfit_count' => $this->outfitCount(), 'updated_at' => $this->updated_at,
        ];
    }
}
