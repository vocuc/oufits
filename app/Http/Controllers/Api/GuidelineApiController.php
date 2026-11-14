<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Guideline;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Quy tắc chủ shop viết trên web (chỉ đọc qua API — worker không sửa quy tắc của chủ shop).
 *   GET /api/guidelines?category=outfit|cover|hook  -> quy tắc ĐANG BẬT, theo thứ tự trên trang
 */
class GuidelineApiController extends Controller
{
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
