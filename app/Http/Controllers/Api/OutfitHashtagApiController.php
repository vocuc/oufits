<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Hashtag;
use App\Models\Outfit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Danh sách hashtag outfit của chủ token, cho lệnh /lap-ke-hoach trên A51 chọn hashtag chủ đề bài lookbook
 * (a90-fe note/xay-kenh-tiktok/bai-viet-mau/lookbook.txt): worker phối đồ tìm outfit theo các hashtag này.
 *
 * GET /api/outfit-hashtags?min=1&with_cover=1
 *   with_cover (mặc định 1): chỉ đếm outfit CÓ ẢNH GỐC (worker phối đồ chỉ dùng outfit có ảnh gốc).
 *   min: bỏ hashtag có ít hơn min outfit.
 *   rules=1: không đếm outfit vi phạm luật cấm phối đã duyệt (như GET /api/outfits?rules=1).
 *   -> { data: [{ name, outfits }], meta: { outfits: tổng outfit được đếm } } (nhiều outfit trước)
 */
class OutfitHashtagApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'min' => ['nullable', 'integer', 'min:1'],
            'with_cover' => ['nullable', 'boolean'],
            'rules' => ['nullable', 'boolean'],
        ]);
        $user = $request->user();
        $withCover = $request->boolean('with_cover', true);
        $min = (int) ($data['min'] ?? 1);

        $outfits = Outfit::query()->where('user_id', $user->id)->when($withCover, fn ($q) => $q->whereNotNull('cover_path'))
            ->when($request->boolean('rules'), fn ($q) => $q->withoutStylingConflicts($user->id));
        $rows = DB::table('hashtag_outfit')
            ->join('hashtags', 'hashtags.id', '=', 'hashtag_outfit.hashtag_id')
            ->whereIn('hashtag_outfit.outfit_id', (clone $outfits)->select('id'))
            ->groupBy('hashtags.name')
            ->havingRaw('COUNT(*) >= ?', [$min])
            ->orderByDesc(DB::raw('COUNT(*)'))
            ->orderBy('hashtags.name')
            ->selectRaw('hashtags.name AS name, COUNT(*) AS outfits')
            ->get()
            ->map(fn ($r) => ['name' => $r->name, 'outfits' => (int) $r->outfits]);

        return response()->json(['data' => $rows, 'meta' => ['outfits' => $outfits->count(), 'with_cover' => $withCover]]);
    }

    /**
     * Số outfit có ĐỦ các hashtag (để lệnh kiểm tra trước 1 bộ hashtag chủ đề).
     * GET /api/outfit-hashtags/count?tags=henho,sangchanh&with_cover=1 (phân cách bằng dấu phẩy) -> { data: { tags, outfits } }
     * rules=1 [&shoe=depquai]: không đếm outfit vi phạm luật cấm phối đã duyệt (như GET /api/outfits?rules=1&shoe=).
     */
    public function count(Request $request): JsonResponse
    {
        $request->validate(['tags' => ['required', 'string', 'max:500'], 'with_cover' => ['nullable', 'boolean'],
            'rules' => ['nullable', 'boolean'], 'shoe' => ['nullable', 'string', 'max:500']]);
        // Phân cách bằng dấu phẩy (hashtag gõ có dấu cách như "Hẹn hò" vẫn ra 1 hashtag "henho")
        $tags = collect(explode(',', (string) $request->query('tags')))
            ->map(fn ($t) => Hashtag::normalize($t))->filter()->unique()->values()->all();
        $count = Outfit::query()->where('user_id', $request->user()->id)
            ->when($request->boolean('with_cover', true), fn ($q) => $q->whereNotNull('cover_path'))
            ->withAllHashtags($tags)
            ->when($request->boolean('rules'), fn ($q) => $q->withoutStylingConflicts($request->user()->id, explode(',', (string) $request->query('shoe', ''))))
            ->count();

        return response()->json(['data' => ['tags' => $tags, 'outfits' => $count]]);
    }
}
