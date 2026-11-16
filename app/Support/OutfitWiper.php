<?php

namespace App\Support;

use App\Models\Hashtag;
use App\Models\Outfit;
use App\Models\OutfitRating;
use App\Models\OutfitRender;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Xoá outfit hàng loạt (không qua sự kiện Eloquent từng dòng cho nhanh).
 * Khoá ngoại CSDL tự xoá outfit_items, outfit_renders, outfit_ratings, hashtag pivot và đặt
 * styling_ideas.outfit_id = null; lớp này xoá thêm FILE ảnh bìa + ảnh Thư viện ảnh trên disk
 * (cascade CSDL không gọi sự kiện deleted nên file sẽ bị bỏ sót).
 */
class OutfitWiper
{
    /** @param  int[]  $ids  outfit của $user */
    public static function wipe(User $user, array $ids): array
    {
        $result = ['outfits' => 0, 'renders' => 0, 'ratings' => 0, 'files' => 0];
        $disk = Outfit::coverDisk();

        foreach (array_chunk($ids, 500) as $chunk) {
            $outfits = Outfit::where('user_id', $user->id)->whereIn('id', $chunk);
            $renders = OutfitRender::whereIn('outfit_id', $chunk);

            $files = array_values(array_filter([
                ...(clone $outfits)->pluck('cover_path')->all(),
                ...(clone $renders)->pluck('image_path')->all(),
                ...(clone $renders)->pluck('thumb_path')->all(),
            ]));

            DB::transaction(function () use ($chunk, $outfits, $renders, &$result) {
                $result['ratings'] += OutfitRating::whereIn('outfit_id', $chunk)->delete();
                $result['renders'] += (clone $renders)->delete();
                $result['outfits'] += (clone $outfits)->delete();
            });

            foreach (array_chunk($files, 200) as $batch) {
                $disk->delete($batch);
            }
            $result['files'] += count($files);
        }

        Hashtag::pruneUnused();

        return $result;
    }
}
