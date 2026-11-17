<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OutfitTheme;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Chủ đề lô phối đồ (worker / Claude).
 *   GET  /api/themes?status=approved -> chủ đề (tags, hooks dạng mảng, outfit_count = số outfit có đủ tag + ảnh gốc)
 *   POST /api/themes {name, tags, hooks, season?, note?, sort?} -> tạo / cập nhật theo name.
 *        tags: chuỗi "a b" hoặc mảng; hooks: chuỗi nhiều dòng hoặc mảng. Tạo mới = chờ duyệt; KHÔNG đổi status (chỉ chủ shop duyệt trên web).
 */
class OutfitThemeApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate(['status' => ['nullable', Rule::in(array_keys(OutfitTheme::STATUSES))]]);
        $rows = $request->user()->outfitThemes()
            ->when($data['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->orderBy('sort')->orderBy('id')->get();

        return response()->json(['data' => $rows->map->toApi()->values()]);
    }

    public function upsert(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'tags' => ['required'],
            'tags.*' => ['nullable', 'string', 'max:100'],
            'hooks' => ['required'],
            'hooks.*' => ['nullable', 'string', 'max:1000'],
            'season' => ['nullable', Rule::in(array_keys(OutfitTheme::SEASONS))],
            'note' => ['nullable', 'string', 'max:5000'],
            'sort' => ['nullable', 'integer', 'min:0'],
        ]);
        $tags = OutfitTheme::normalizeTags($data['tags']);
        $hooks = OutfitTheme::normalizeHooks($data['hooks']);
        if ($tags === '' || $hooks === '') {
            return response()->json(['message' => 'Cần ít nhất 1 hashtag và 1 câu hook.',
                'errors' => ['tags' => ['Cần ít nhất 1 hashtag và 1 câu hook.']]], 422);
        }
        $user = $request->user();
        $fields = ['tags' => $tags, 'hooks' => $hooks, 'season' => $data['season'] ?? null, 'note' => $data['note'] ?? null];
        if (isset($data['sort'])) {
            $fields['sort'] = $data['sort'];
        }
        $theme = $user->outfitThemes()->where('name', trim($data['name']))->first();
        $created = ! $theme;
        if ($created) {
            $theme = $user->outfitThemes()->create($fields + [
                'name' => trim($data['name']), 'status' => OutfitTheme::PENDING,
                'sort' => $fields['sort'] ?? (int) $user->outfitThemes()->max('sort') + 1,
            ]);
        } else {
            $theme->update($fields);
        }

        return response()->json(['data' => $theme->fresh()->toApi(), 'created' => $created], $created ? 201 : 200);
    }
}
