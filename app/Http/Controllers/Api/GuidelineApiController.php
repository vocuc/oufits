<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Guideline;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Quy tắc chủ shop viết trên web. API không xoá quy tắc; sửa chỉ khi chủ shop yêu cầu (Claude sửa theo lời chủ shop).
 *   GET  /api/guidelines?category=outfit|cover|hook  -> quy tắc ĐANG BẬT, theo thứ tự trên trang (all=1: cả quy tắc đã tắt)
 *   POST /api/guidelines {category, body}             -> thêm vào cuối mục; trùng nội dung trong mục thì bỏ qua (200)
 *   PUT  /api/guidelines/{id} {body?, is_active?}     -> sửa nội dung / bật tắt một quy tắc
 */
class GuidelineApiController extends Controller
{
    public function update(Request $request, int $id): JsonResponse
    {
        $rule = $request->user()->guidelines()->findOrFail($id);
        $data = $request->validate([
            'body' => ['sometimes', 'required', 'string', 'max:5000'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        if (isset($data['body'])) {
            $data['body'] = trim($data['body']);
        }
        $rule->update($data);

        return response()->json(['data' => $rule->fresh()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'category' => ['required', Rule::in(array_keys(Guideline::CATEGORIES))],
            'body' => ['required', 'string', 'max:5000'],
        ]);
        $body = trim($data['body']);
        $rules = $request->user()->guidelines()->where('category', $data['category']);
        if ($existing = (clone $rules)->where('body', $body)->first()) {
            return response()->json(['data' => $existing, 'created' => false]);
        }
        $rule = $request->user()->guidelines()->create([
            'category' => $data['category'], 'body' => $body, 'sort' => (int) (clone $rules)->max('sort') + 1,
        ]);

        return response()->json(['data' => $rule, 'created' => true], 201);
    }

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'category' => ['nullable', Rule::in(array_keys(Guideline::CATEGORIES))],
            'all' => ['nullable', 'boolean'],
        ]);
        $rows = $request->user()->guidelines()
            ->when(! $request->boolean('all'), fn ($q) => $q->where('is_active', true))
            ->when($data['category'] ?? null, fn ($q, $c) => $q->where('category', $c))
            ->orderBy('category')->orderBy('sort')->orderBy('id')
            ->get(['id', 'category', 'body', 'is_active', 'sort', 'updated_at']);

        return response()->json(['data' => $rows]);
    }
}
