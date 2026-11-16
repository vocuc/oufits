<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\UploadedFile;

/**
 * Người mẫu dùng khi vẽ ảnh outfit: ảnh + ghi chú của chủ shop, prompt mô tả người mẫu (Claude viết, chủ shop sửa được).
 * Mỗi tài khoản có tối đa 1 người mẫu mặc định (is_default).
 */
class FashionModel extends Model
{
    protected $table = 'fashion_models';

    protected $guarded = ['id'];

    protected $hidden = ['user_id'];

    protected function casts(): array
    {
        return ['is_default' => 'boolean', 'content_updated_at' => 'datetime', 'prompt_updated_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(ModelPhoto::class)->orderBy('sort')->orderBy('id');
    }

    /** Chưa có prompt, hoặc ghi chú / ảnh đổi sau lần viết prompt gần nhất. */
    public function needsPrompt(): bool
    {
        if (blank($this->prompt)) {
            return true;
        }

        return $this->content_updated_at && (! $this->prompt_updated_at || $this->content_updated_at->gt($this->prompt_updated_at));
    }

    public function touchContent(): void
    {
        $this->forceFill(['content_updated_at' => now()])->save();
    }

    /** @param  UploadedFile[]  $files */
    public function addPhotos(array $files): int
    {
        $sort = (int) $this->photos()->max('sort');
        foreach ($files as $file) {
            $photo = $this->photos()->create(['path' => '', 'sort' => ++$sort]);
            $photo->store($file);
        }
        if ($files) {
            $this->touchContent();
        }

        return count($files);
    }

    public function setPrompt(?string $prompt): void
    {
        $prompt = trim(str_replace("\r\n", "\n", (string) $prompt));
        $this->forceFill(['prompt' => $prompt === '' ? null : $prompt, 'prompt_updated_at' => now()])->save();
    }

    /** Đặt làm người mẫu mặc định (bỏ mặc định của người mẫu khác cùng tài khoản). */
    public function makeDefault(): void
    {
        static::query()->where('user_id', $this->user_id)->whereKeyNot($this->id)->update(['is_default' => false]);
        $this->forceFill(['is_default' => true])->save();
    }

    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'notes' => $this->notes,
            'prompt' => $this->prompt,
            'is_default' => $this->is_default,
            'needs_prompt' => $this->needsPrompt(),
            'content_updated_at' => $this->content_updated_at?->toIso8601String(),
            'prompt_updated_at' => $this->prompt_updated_at?->toIso8601String(),
            'photos' => $this->photos->map(fn (ModelPhoto $p) => [
                'id' => $p->id,
                'url' => url("/api/models/{$this->id}/photos/{$p->id}"),
            ])->values(),
        ];
    }

    protected static function booted(): void
    {
        // xoá file ảnh trên disk (bản ghi ảnh tự xoá theo khoá ngoại cascade)
        static::deleting(function (FashionModel $m) {
            $m->photos->each->deleteFiles();
        });
    }
}
