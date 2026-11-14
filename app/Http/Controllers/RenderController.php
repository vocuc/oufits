<?php

namespace App\Http\Controllers;

use App\Models\Outfit;
use App\Models\OutfitRender;
use Illuminate\Http\Request;

/** Trang "Thư viện ảnh": ảnh gốc của lô phối đồ, lọc theo outfit (?outfit=) hoặc sản phẩm A51 (?product=). */
class RenderController extends Controller
{
    private const PER_PAGE = 40;

    public function index(Request $request)
    {
        $user = $request->user();
        $outfitId = $request->integer('outfit') ?: null;
        $productId = $request->integer('product') ?: null;
        $outfit = $outfitId ? $user->outfits()->find($outfitId) : null;

        $renders = OutfitRender::query()->where('user_id', $user->id)
            ->when($outfitId, fn ($q) => $q->where('outfit_id', $outfitId))
            ->when($productId, fn ($q) => $q->where('a51_product_id', $productId))
            ->with('outfit:id,title')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)->withQueryString();

        return view('renders.index', compact('renders', 'outfit', 'productId'));
    }

    public function image(Request $request, OutfitRender $render)
    {
        abort_unless($render->user_id === $request->user()->id, 404);
        $path = $request->boolean('thumb') && $render->thumb_path ? $render->thumb_path : $render->image_path;
        $disk = Outfit::coverDisk();
        if ($disk->providesTemporaryUrls()) {
            return redirect()->away($disk->temporaryUrl($path, now()->addHour()))->header('Cache-Control', 'private, max-age=3000');
        }
        abort_unless($disk->exists($path), 404);

        return $disk->response($path, null, ['Cache-Control' => 'private, max-age=31536000, immutable']);
    }
}
