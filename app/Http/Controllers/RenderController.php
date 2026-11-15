<?php

namespace App\Http\Controllers;

use App\Models\Outfit;
use App\Models\OutfitRender;
use App\Support\StylingEngine;
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
        $rated = in_array($request->query('rated'), ['0', '1'], true) ? $request->query('rated') : null;

        $renders = OutfitRender::query()->where('user_id', $user->id)
            ->when($outfitId, fn ($q) => $q->where('outfit_id', $outfitId))
            ->when($productId, fn ($q) => $q->where('a51_product_id', $productId))
            // ?rated=0: ảnh chưa chấm điểm, ?rated=1: ảnh đã chấm
            ->when($rated === '0', fn ($q) => $q->whereNull('rating'))
            ->when($rated === '1', fn ($q) => $q->whereNotNull('rating'))
            ->with('outfit:id,title')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)->withQueryString();

        return view('renders.index', compact('renders', 'outfit', 'productId', 'rated'));
    }

    /**
     * Chủ shop chấm ảnh 1-10 (rating rỗng = bỏ điểm), rồi học lại phần điểm chấm (fb / fb_shoe) ngay.
     * Bấm từ form trên Thư viện ảnh -> quay lại đúng ảnh vừa chấm.
     */
    public function rate(Request $request, OutfitRender $render)
    {
        abort_unless($render->user_id === $request->user()->id, 404);
        $data = $request->validate(['rating' => ['nullable', 'integer', 'min:1', 'max:10']]);
        $rating = isset($data['rating']) ? (int) $data['rating'] : null;
        if ($render->rating !== $rating) {
            $render->update(['rating' => $rating, 'rated_at' => $rating === null ? null : now()]);
            (new StylingEngine($request->user()))->learnFeedback();
        }

        if ($request->expectsJson()) {
            return response()->json(['data' => ['id' => $render->id, 'rating' => $render->rating]]);
        }

        return redirect()->to(url()->previous(route('renders.index')).'#render-'.$render->id);
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
