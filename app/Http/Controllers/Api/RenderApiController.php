<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OutfitRender;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Ảnh gốc của lô phối đồ (worker phối đồ gửi lên).
 *   POST /api/renders (multipart) image, outfit_id, a51_user_id?, a51_product_id?, a51_product_name?, a51_batch_id?, a51_output_id?
 *   GET  /api/renders/used-outfits?a51_product_id=&a51_user_id=   -> outfit đã dùng cho đôi giày đó (không lặp outfit)
 */
class RenderApiController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:20480'],
            'outfit_id' => ['required', 'integer', Rule::exists('outfits', 'id')->where('user_id', $user->id)],
            'a51_user_id' => ['nullable', 'integer'],
            'a51_product_id' => ['nullable', 'integer'],
            'a51_product_name' => ['nullable', 'string', 'max:255'],
            'a51_batch_id' => ['nullable', 'integer'],
            'a51_output_id' => ['nullable', 'integer'],
        ]);
        [$path, $thumb] = OutfitRender::storeImage($user->id, $request->file('image'));
        $render = OutfitRender::query()->create([
            ...collect($data)->except('image')->all(),
            'user_id' => $user->id, 'image_path' => $path, 'thumb_path' => $thumb,
        ]);

        return response()->json(['data' => [...$render->toArray(), 'url' => $render->url()]], 201);
    }

    public function usedOutfits(Request $request): JsonResponse
    {
        $data = $request->validate([
            'a51_product_id' => ['required', 'integer'],
            'a51_user_id' => ['nullable', 'integer'],
        ]);
        $ids = OutfitRender::query()->where('user_id', $request->user()->id)
            ->where('a51_product_id', $data['a51_product_id'])
            ->when(isset($data['a51_user_id']), fn ($q) => $q->where('a51_user_id', $data['a51_user_id']))
            ->distinct()->orderBy('outfit_id')->pluck('outfit_id');

        return response()->json(['data' => $ids]);
    }
}
