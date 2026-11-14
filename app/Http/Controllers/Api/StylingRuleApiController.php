<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StylingRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Kiến thức phối đồ của tài khoản sở hữu token.
 *   GET    /api/styling-rules?shoe_type=&dimension=&source=&min_score=&with_general=1
 *          có shoe_type -> kèm dòng chung (shoe_type null), trừ khi with_general=0
 *   PUT    /api/styling-rules { rules: [...] }   upsert theo (shoe_type, dimension, value, source)
 *   DELETE /api/styling-rules/{id}
 * Nhận định của người dùng (source=user) không ghi / xoá qua API (Claude dùng API).
 */
class StylingRuleApiController extends Controller
{
    public const MAX_RULES = 500;

    private const SLUG = 'regex:/^[a-z0-9+_-]+$/';

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'shoe_type' => ['nullable', 'string', 'max:40'],
            'dimension' => ['nullable', Rule::in(StylingRule::DIMENSIONS)],
            'source' => ['nullable', Rule::in(StylingRule::SOURCES)],
            'min_score' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'with_general' => ['nullable', 'boolean'],
        ]);

        $query = $request->user()->stylingRules();
        if (($data['shoe_type'] ?? null) !== null) {
            $withGeneral = $request->boolean('with_general', true);
            $query->where(fn ($q) => $q->where('shoe_type', $data['shoe_type'])
                ->when($withGeneral, fn ($q) => $q->orWhereNull('shoe_type')));
        }
        foreach (['dimension', 'source'] as $field) {
            if (isset($data[$field])) {
                $query->where($field, $data[$field]);
            }
        }
        if (isset($data['min_score'])) {
            $query->where('score', '>=', $data['min_score']);
        }

        return response()->json([
            'data' => $query->orderBy('shoe_type')->orderBy('dimension')->orderByDesc('score')->orderBy('id')->get(),
        ]);
    }

    public function upsert(Request $request): JsonResponse
    {
        $data = $request->validate([
            'rules' => ['required', 'array', 'min:1', 'max:'.self::MAX_RULES],
            'rules.*.shoe_type' => ['nullable', 'string', 'max:40', self::SLUG],
            'rules.*.dimension' => ['required', Rule::in(StylingRule::DIMENSIONS)],
            'rules.*.value' => ['required', 'string', 'max:60', self::SLUG],
            'rules.*.relation' => ['required', Rule::in(StylingRule::RELATIONS)],
            'rules.*.score' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'rules.*.evidence_count' => ['nullable', 'integer', 'min:0'],
            'rules.*.note' => ['nullable', 'string', 'max:2000'],
            'rules.*.examples' => ['nullable', 'array', 'max:20'],
            'rules.*.source' => ['required', Rule::in(array_values(array_diff(StylingRule::SOURCES, ['user'])))],
        ], [
            'rules.*.source.in' => 'source phải là ootd_stats, tiktok hoặc claude (user chỉ do người dùng nhập).',
        ]);

        $user = $request->user();
        $result = ['created' => 0, 'updated' => 0];
        DB::transaction(function () use ($data, $user, &$result) {
            foreach ($data['rules'] as $row) {
                $rule = $user->stylingRules()->firstOrNew([
                    'shoe_key' => (string) ($row['shoe_type'] ?? ''),
                    'dimension' => $row['dimension'],
                    'value' => $row['value'],
                    'source' => $row['source'],
                ]);
                $result[$rule->exists ? 'updated' : 'created']++;
                $rule->fill([
                    'shoe_type' => $row['shoe_type'] ?? null,
                    'relation' => $row['relation'],
                    'score' => $row['score'] ?? 0,
                    'evidence_count' => $row['evidence_count'] ?? 0,
                    'note' => $row['note'] ?? null,
                    'examples' => $row['examples'] ?? null,
                ])->save();
            }
        });

        return response()->json(['data' => $result]);
    }

    public function destroy(Request $request, int $id): Response|JsonResponse
    {
        $rule = $request->user()->stylingRules()->findOrFail($id);
        if ($rule->source === 'user') {
            return response()->json(['message' => 'Nhận định của người dùng, không xoá qua API.'], 409);
        }
        $rule->delete();

        return response()->noContent();
    }
}
