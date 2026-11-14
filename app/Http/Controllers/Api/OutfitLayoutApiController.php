<?php

namespace App\Http\Controllers\Api;

use App\Models\OutfitLayout;

/**
 * Mẫu ảnh outfit (worker / Claude).
 *   GET  /api/outfit-layouts?status=approved -> các layout (kèm prompt, status, request)
 *   POST /api/outfit-layouts (multipart) code, name, hook_type?, how_to?, prompt?, fix_prompt?, sort?, image? -> tạo / cập nhật theo code.
 *        Không đổi status / request (chỉ chủ shop sửa trên web).
 */
class OutfitLayoutApiController extends CoverTemplateApiController
{
    protected string $model = OutfitLayout::class;

    protected array $fields = ['name', 'hook_type', 'how_to', 'prompt', 'fix_prompt', 'sort'];

    protected function rules(): array
    {
        return [...parent::rules(), 'prompt' => ['nullable', 'string', 'max:30000'], 'fix_prompt' => ['nullable', 'string', 'max:20000']];
    }
}
