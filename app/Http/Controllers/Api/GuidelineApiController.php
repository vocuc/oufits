<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Guideline;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Quy tắc chủ shop viết trên web. API không sửa / xoá quy tắc có sẵn (chỉ chủ shop sửa trên web).
 *   GET  /api/guidelines?category=outfit|cover|hook  -> quy tắc ĐANG BẬT, theo thứ tự trên trang
 *   POST /api/guidelines {category, body}             -> thêm vào cuối mục; trùng nội dung trong mục thì bỏ qua (200)
 */
class GuidelineApiController extends Controller
{
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
        $data = $request->validate(['category' => ['nullable', Rule::in(array_keys(Guideline::CATEGORIES))]]);
        $rows = $request->user()->guidelines()->where('is_active', true)
            ->when($data['category'] ?? null, fn ($q, $c) => $q->where('category', $c))
            ->orderBy('category')->orderBy('sort')->orderBy('id')
            ->get(['id', 'category', 'body', 'sort', 'updated_at']);

        return response()->json(['data' => $rows]);
    }
}
