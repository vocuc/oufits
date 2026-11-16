<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FashionModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Người mẫu (Claude / worker).
 *   GET /api/models[?needs_prompt=1]        -> người mẫu: tên, ghi chú, prompt, is_default, needs_prompt, ảnh (url API)
 *   GET /api/models/{id}/photos/{photo}     -> file ảnh người mẫu (?thumb=1 bản nhỏ)
 *   PUT /api/models/{id}/prompt {prompt}    -> ghi prompt mô tả người mẫu (hết nhãn "cần viết lại")
 * API không sửa tên / ghi chú / ảnh (chỉ chủ shop sửa trên web).
 */
class FashionModelApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate(['needs_prompt' => ['nullable', 'boolean']]);
        $rows = $request->user()->fashionModels()->with('photos')
            ->orderByDesc('is_default')->orderBy('name')->orderBy('id')->get();
        if ($request->boolean('needs_prompt')) {
            $rows = $rows->filter->needsPrompt();
        }

        return response()->json(['data' => $rows->map->toApi()->values()]);
    }

    public function photo(Request $request, string $model, string $photo)
    {
        return $this->find($request, $model)->photos()->whereKey((int) $photo)->firstOrFail()
            ->response($request->boolean('thumb'));
    }

    public function prompt(Request $request, string $model): JsonResponse
    {
        $m = $this->find($request, $model);
        $data = $request->validate(['prompt' => ['required', 'string', 'max:20000']]);
        $m->setPrompt($data['prompt']);

        return response()->json(['data' => $m->load('photos')->toApi()]);
    }

    private function find(Request $request, string $id): FashionModel
    {
        return $request->user()->fashionModels()->whereKey((int) $id)->firstOrFail();
    }
}
