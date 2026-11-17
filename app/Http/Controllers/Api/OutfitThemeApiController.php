<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OutfitThemeHook;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Chủ đề (chỉ gom nhóm) + hook (hashtag riêng, duyệt riêng) cho worker / Claude.
 *   GET  /api/themes?status=approved -> chủ đề kèm hooks [{id, body, tags[], status, outfit_count}]; có status thì chỉ
 *        hook ở trạng thái đó và bỏ chủ đề không còn hook nào.
 *   POST /api/themes {name, note?, sort?, hooks?: [{body, tags}]} -> tạo / cập nhật chủ đề theo name; hook so theo body:
 *        mới = chờ duyệt, đã có = sửa tags. KHÔNG đổi status, KHÔNG xoá hook không gửi (chủ shop quản lý trên web).
 *   PUT  /api/theme-hooks/{id} {body?, tags?} -> sửa câu / hashtag một hook (không đổi status).
 */
class OutfitThemeApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate(['status' => ['nullable', Rule::in(array_keys(OutfitThemeHook::STATUSES))]]);
        $status = $data['status'] ?? null;
        $rows = $request->user()->outfitThemes()->with('hooks.theme')->orderBy('sort')->orderBy('id')->get()
            ->map->toApi($status)
            ->when($status, fn ($c) => $c->filter(fn ($t) => $t['hooks']))
            ->values();

        return response()->json(['data' => $rows]);
    }

    public function upsert(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:5000'],
            'sort' => ['nullable', 'integer', 'min:0'],
            'hooks' => ['nullable', 'array', 'max:100'],
            'hooks.*.body' => ['required', 'string', 'max:500'],
            'hooks.*.tags' => ['required'],
        ]);
        $hooks = collect($data['hooks'] ?? [])->map(fn ($h) => [
            'body' => OutfitThemeHook::normalizeBody($h['body']), 'tags' => OutfitThemeHook::normalizeTags($h['tags']),
        ]);
        if ($hooks->contains(fn ($h) => $h['body'] === '' || $h['tags'] === '')) {
            return response()->json(['message' => 'Mỗi hook cần câu hook và ít nhất 1 hashtag.',
                'errors' => ['hooks' => ['Mỗi hook cần câu hook và ít nhất 1 hashtag.']]], 422);
        }
        $user = $request->user();
        $name = trim($data['name']);
        $theme = $user->outfitThemes()->where('name', $name)->first();
        $created = ! $theme;
        $fields = array_filter(['note' => $data['note'] ?? null, 'sort' => $data['sort'] ?? null], fn ($v) => $v !== null);
        if ($created) {
            $theme = $user->outfitThemes()->create($fields + ['name' => $name, 'sort' => (int) $user->outfitThemes()->max('sort') + 1]);
        } elseif ($fields) {
            $theme->update($fields);
        }
        $sort = (int) $theme->hooks()->max('sort');
        foreach ($hooks->unique('body') as $h) {
            $hook = $theme->hooks()->where('body', $h['body'])->first();
            $hook ? $hook->update(['tags' => $h['tags']])
                : $theme->hooks()->create($h + ['status' => OutfitThemeHook::PENDING, 'sort' => ++$sort]);
        }

        return response()->json(['data' => $theme->fresh()->load('hooks.theme')->toApi(), 'created' => $created], $created ? 201 : 200);
    }

    public function updateHook(Request $request, int $id): JsonResponse
    {
        $hook = OutfitThemeHook::query()->whereHas('theme', fn ($q) => $q->where('user_id', $request->user()->id))->findOrFail($id);
        $data = $request->validate([
            'body' => ['sometimes', 'required', 'string', 'max:500'],
            'tags' => ['sometimes', 'required'],
        ]);
        $fields = [];
        if (isset($data['body'])) {
            $fields['body'] = OutfitThemeHook::normalizeBody($data['body']);
        }
        if (isset($data['tags'])) {
            $fields['tags'] = OutfitThemeHook::normalizeTags($data['tags']);
        }
        if (in_array('', $fields, true)) {
            return response()->json(['message' => 'Hook cần câu hook và ít nhất 1 hashtag.'], 422);
        }
        $hook->update($fields);

        return response()->json(['data' => $hook->fresh()->toApi()]);
    }
}
