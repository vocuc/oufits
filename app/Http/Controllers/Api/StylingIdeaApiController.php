<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StylingIdea;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Ý tưởng phối đồ học từ ảnh (kèm cách thực hiện) của tài khoản sở hữu token.
 *   GET    /api/styling-ideas?shoe_types=giaysneaker&occasions=henho,dichoi&min_rating=4&q=&order=new|fresh|rating
 *          mỗi cột danh sách: có ít nhất một giá trị; nhiều cột: phải khớp mọi cột
 *   GET    /api/styling-ideas/keys?source=tiktok   -> mọi source_key đã học (không học lại ảnh cũ)
 *   PUT    /api/styling-ideas { ideas: [...] }    upsert theo source_key
 *   POST   /api/styling-ideas/{id}/used           đã dùng cho 1 ảnh phối đồ (times_used + 1)
 *   DELETE /api/styling-ideas/{id}
 * Ý tưởng người dùng đã duyệt (is_confirmed) hoặc source=user: không ghi đè / xoá qua API.
 */
class StylingIdeaApiController extends Controller
{
    public const MAX_IDEAS = 200;

    public const MAX_LIMIT = 200;

    private const SLUG = 'regex:/^[a-z0-9_-]+$/';

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            ...array_fill_keys(StylingIdea::LIST_FIELDS, ['nullable', 'string', 'max:500']),
            'source' => ['nullable', Rule::in(StylingIdea::SOURCES)],
            'min_rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'q' => ['nullable', 'string', 'max:100'],
            'order' => ['nullable', 'in:new,fresh,rating'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_LIMIT],
            'offset' => ['nullable', 'integer', 'min:0'],
        ]);

        $query = $request->user()->stylingIdeas();
        foreach (StylingIdea::LIST_FIELDS as $field) {
            $values = array_values(array_filter(array_map('trim', explode(',', (string) ($data[$field] ?? '')))));
            if ($values) {
                $query->where(function ($q) use ($field, $values) {
                    foreach ($values as $value) {
                        $q->orWhereJsonContains($field, $value);
                    }
                });
            }
        }
        if (isset($data['source'])) {
            $query->where('source', $data['source']);
        }
        if (isset($data['min_rating'])) {
            $query->where('rating', '>=', $data['min_rating']);
        }
        if (isset($data['q'])) {
            $like = '%'.addcslashes($data['q'], '%_\\').'%';
            $query->where(fn ($q) => $q->where('title', 'like', $like)->orWhere('idea', 'like', $like)
                ->orWhere('how_to', 'like', $like));
        }

        $total = (clone $query)->count();
        match ($data['order'] ?? 'new') {
            'fresh' => $query->orderBy('times_used')->orderByDesc('rating')->orderByDesc('id'),
            'rating' => $query->orderByDesc('rating')->orderByDesc('id'),
            default => $query->orderByDesc('id'),
        };
        $limit = (int) ($data['limit'] ?? 50);
        $offset = (int) ($data['offset'] ?? 0);
        $ideas = $query->skip($offset)->take($limit)->get();

        return response()->json([
            'data' => $ideas,
            'meta' => [
                'total' => $total,
                'limit' => $limit,
                'offset' => $offset,
                'next_offset' => $offset + $ideas->count() < $total ? $offset + $ideas->count() : null,
            ],
        ]);
    }

    public function keys(Request $request): JsonResponse
    {
        $data = $request->validate(['source' => ['nullable', Rule::in(StylingIdea::SOURCES)]]);

        $keys = $request->user()->stylingIdeas()
            ->when($data['source'] ?? null, fn ($q, $s) => $q->where('source', $s))
            ->orderBy('id')->pluck('source_key');

        return response()->json(['data' => $keys]);
    }

    public function upsert(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'ideas' => ['required', 'array', 'min:1', 'max:'.self::MAX_IDEAS],
            'ideas.*.source_key' => ['required', 'string', 'max:120', 'distinct'],
            'ideas.*.title' => ['required', 'string', 'max:200'],
            'ideas.*.idea' => ['required', 'string', 'max:5000'],
            'ideas.*.how_to' => ['required', 'string', 'max:10000'],
            ...collect(StylingIdea::LIST_FIELDS)->flatMap(fn ($f) => [
                "ideas.*.$f" => ['nullable', 'array', 'max:30'],
                "ideas.*.$f.*" => ['string', 'max:60', self::SLUG],
            ])->all(),
            'ideas.*.age_range' => ['nullable', 'string', 'max:10', 'regex:/^\d{1,2}-\d{1,2}$/'],
            'ideas.*.source' => ['required', Rule::in(array_values(array_diff(StylingIdea::SOURCES, ['user'])))],
            'ideas.*.source_url' => ['nullable', 'string', 'max:500'],
            'ideas.*.source_author' => ['nullable', 'string', 'max:100'],
            'ideas.*.image_ref' => ['nullable', 'string', 'max:255'],
            'ideas.*.outfit_id' => ['nullable', 'integer', Rule::exists('outfits', 'id')->where('user_id', $user->id)],
            'ideas.*.rating' => ['nullable', 'integer', 'min:1', 'max:5'],
        ], [
            'ideas.*.source.in' => 'source phải là tiktok, ootd hoặc claude (user chỉ do người dùng nhập).',
        ]);

        $result = ['created' => 0, 'updated' => 0, 'skipped' => 0];
        $fields = ['title', 'idea', 'how_to', ...StylingIdea::LIST_FIELDS, 'age_range', 'source', 'source_url',
            'source_author', 'image_ref', 'outfit_id'];
        DB::transaction(function () use ($data, $user, $fields, &$result) {
            foreach ($data['ideas'] as $row) {
                $idea = $user->stylingIdeas()->firstOrNew(['source_key' => $row['source_key']]);
                if ($idea->exists && ($idea->is_confirmed || $idea->source === 'user')) {
                    $result['skipped']++;

                    continue;
                }
                $result[$idea->exists ? 'updated' : 'created']++;
                foreach ($fields as $field) {
                    $idea->{$field} = $row[$field] ?? null;
                }
                $idea->rating = $row['rating'] ?? 3;
                $idea->save();
            }
        });

        return response()->json(['data' => $result]);
    }

    public function used(Request $request, int $id): JsonResponse
    {
        $idea = $request->user()->stylingIdeas()->findOrFail($id);
        $idea->forceFill(['times_used' => $idea->times_used + 1, 'last_used_at' => now()])->save();

        return response()->json(['data' => $idea]);
    }

    public function destroy(Request $request, int $id): Response|JsonResponse
    {
        $idea = $request->user()->stylingIdeas()->findOrFail($id);
        if ($idea->is_confirmed || $idea->source === 'user') {
            return response()->json(['message' => 'Ý tưởng người dùng đã duyệt, không xoá qua API.'], 409);
        }
        $idea->delete();

        return response()->noContent();
    }
}
