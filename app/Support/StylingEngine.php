<?php

namespace App\Support;

use App\Models\ItemPair;
use App\Models\Outfit;
use App\Models\OutfitItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Học độ hợp giữa các món (item_pairs), chấm điểm một bộ món, gợi ý bộ món mới.
 * Thiết kế: D:\FlowKit\a51-skills\outfit-video\thiet-ke-item.txt
 */
class StylingEngine
{
    /** Hashtag ngữ cảnh (dịp, mùa) dùng cho cấp cat_ctx. */
    public const CONTEXT_TAGS = ['dichoi', 'henho', 'dicafe', 'dilam', 'dihoc', 'dutiec', 'dulich', 'dutet',
        'thudong', 'xuanthu', 'xuanhe'];

    /** Cặp có count < MIN_COUNT chỉ lưu khi expected >= NEG_EXPECTED (bằng chứng "hiếm đi cùng"). */
    public const MIN_COUNT = 2;

    public const NEG_EXPECTED = 3.0;

    /** Trọng số "điểm 0" cộng vào mẫu số khi chấm (làm mượt bộ ít cặp). */
    public const PRIOR_WEIGHT = 3.0;

    /** Mỗi món chính (đầm / phần dưới cùng loại + màu) xuất hiện tối đa bấy nhiêu lần trong kết quả gợi ý. */
    public const MAX_SAME_MAIN = 2;

    private const NEG_LEVELS = ['cat', 'color', 'material', 'pattern', 'cat_shoe'];

    /** @var array<string, array{0: float, 1: int}>|null */
    private ?array $pairs = null;

    public function __construct(private User $user) {}

    // ------------------------------------------------------------------ học

    /** Tính lại toàn bộ item_pairs của tài khoản. Trả về số outfit đã dùng và số cặp theo cấp. */
    public function learn(): array
    {
        $items = OutfitItem::query()->where('user_id', $this->user->id)->orderBy('outfit_id')->orderBy('position')->get();
        $byOutfit = $items->groupBy('outfit_id');
        $tags = DB::table('hashtag_outfit')->join('hashtags', 'hashtags.id', '=', 'hashtag_outfit.hashtag_id')
            ->whereIn('hashtag_outfit.outfit_id', $byOutfit->keys())
            ->get(['hashtag_outfit.outfit_id', 'hashtags.name'])->groupBy('outfit_id')
            ->map(fn ($rows) => $rows->pluck('name')->all());

        $n = $byOutfit->count();
        $sets = []; // level => outfit_id => [token,...]  (cấp "trong outfit")
        $cross = ['cat_shoe' => [], 'color_shoe' => [], 'cat_ctx' => []]; // level => outfit_id => [[a..],[b..]]
        foreach ($byOutfit as $outfitId => $list) {
            $t = ['cat' => [], 'cat_color' => [], 'color' => [], 'material' => [], 'pattern' => [], 'style_full' => []];
            foreach ($list as $item) {
                foreach (self::tokens($item->toArray()) as $level => $values) {
                    foreach ($values as $v) {
                        $t[$level][$v] = true;
                    }
                }
            }
            foreach ($t as $level => $values) {
                $sets[$level][$outfitId] = array_keys($values);
            }
            $outfitTags = $tags[$outfitId] ?? [];
            $shoes = array_values(array_filter($outfitTags, fn ($h) => str_starts_with($h, 'giay') || str_starts_with($h, 'boot')));
            $ctx = array_values(array_intersect($outfitTags, self::CONTEXT_TAGS));
            $cross['cat_shoe'][$outfitId] = [array_keys($t['cat']), $shoes];
            $cross['color_shoe'][$outfitId] = [array_keys($t['color']), $shoes];
            $cross['cat_ctx'][$outfitId] = [array_keys($t['cat']), $ctx];
        }

        $rows = [];
        foreach ($sets as $level => $perOutfit) {
            $rows = [...$rows, ...$this->pairsWithin($level, $perOutfit, $n)];
        }
        foreach ($cross as $level => $perOutfit) {
            $rows = [...$rows, ...$this->pairsAcross($level, $perOutfit, $n)];
        }

        DB::transaction(function () use ($rows) {
            ItemPair::query()->where('user_id', $this->user->id)->delete();
            $now = now();
            foreach (array_chunk($rows, 500) as $chunk) {
                ItemPair::query()->insert(array_map(fn ($r) => [
                    ...$r, 'user_id' => $this->user->id, 'examples' => json_encode($r['examples']),
                    'created_at' => $now, 'updated_at' => $now,
                ], $chunk));
            }
        });
        $this->pairs = null;

        return ['outfits' => $n, 'items' => $items->count(), 'pairs' => collect($rows)->countBy('level')->all()];
    }

    /** Các token của 1 món theo từng cấp. */
    public static function tokens(array $item): array
    {
        $cat = $item['category'] ?? null;
        if (! $cat) {
            return [];
        }
        $colors = array_values(array_filter([$item['color'] ?? null, $item['color2'] ?? null]));
        $full = implode(':', [$cat, $item['color'] ?? '-', $item['material'] ?? '-', $item['fit'] ?? '-']);

        return [
            'cat' => [$cat],
            'cat_color' => ! empty($item['color']) ? ["{$cat}:{$item['color']}"] : [],
            'color' => $colors,
            'material' => array_values(array_filter([$item['material'] ?? null])),
            'pattern' => array_values(array_filter([$item['pattern'] ?? null])),
            'style_full' => [$full],
        ];
    }

    private function pairsWithin(string $level, array $perOutfit, int $n): array
    {
        $freq = [];
        $count = [];
        $examples = [];
        foreach ($perOutfit as $outfitId => $tokens) {
            sort($tokens);
            foreach ($tokens as $t) {
                $freq[$t] = ($freq[$t] ?? 0) + 1;
            }
            $m = count($tokens);
            for ($i = 0; $i < $m; $i++) {
                for ($j = $i + 1; $j < $m; $j++) {
                    $key = $tokens[$i]."\x1F".$tokens[$j];
                    $count[$key] = ($count[$key] ?? 0) + 1;
                    if (count($examples[$key] ?? []) < 10) {
                        $examples[$key][] = $outfitId;
                    }
                }
            }
        }

        return $this->buildRows($level, $freq, $freq, $count, $examples, $n, true);
    }

    private function pairsAcross(string $level, array $perOutfit, int $n): array
    {
        $freqA = [];
        $freqB = [];
        $count = [];
        $examples = [];
        foreach ($perOutfit as $outfitId => [$as, $bs]) {
            foreach ($as as $a) {
                $freqA[$a] = ($freqA[$a] ?? 0) + 1;
            }
            foreach ($bs as $b) {
                $freqB[$b] = ($freqB[$b] ?? 0) + 1;
            }
            foreach ($as as $a) {
                foreach ($bs as $b) {
                    $key = $a."\x1F".$b;
                    $count[$key] = ($count[$key] ?? 0) + 1;
                    if (count($examples[$key] ?? []) < 10) {
                        $examples[$key][] = $outfitId;
                    }
                }
            }
        }

        return $this->buildRows($level, $freqA, $freqB, $count, $examples, $n, false);
    }

    private function buildRows(string $level, array $freqA, array $freqB, array $count, array $examples, int $n, bool $symmetric): array
    {
        if ($n === 0) {
            return [];
        }
        $rows = [];
        $row = function (string $a, string $b, int $c) use ($level, $freqA, $freqB, $n, $examples) {
            $expected = $freqA[$a] * $freqB[$b] / $n;

            return [
                'level' => $level, 'a' => $a, 'b' => $b, 'count' => $c,
                'expected' => round($expected, 3),
                'lift' => round($expected > 0 ? $c / $expected : 0, 3),
                'score' => round(tanh(log(($c + 0.5) / ($expected + 0.5))), 3),
                'examples' => $examples[$a."\x1F".$b] ?? [],
            ];
        };
        foreach ($count as $key => $c) {
            if ($c >= self::MIN_COUNT) {
                [$a, $b] = explode("\x1F", $key);
                $rows[] = $row($a, $b, $c);
            }
        }
        if (in_array($level, self::NEG_LEVELS, true)) {
            // cặp hay gặp riêng lẻ nhưng hiếm / không bao giờ đi cùng -> điểm âm
            $as = array_keys(array_filter($freqA, fn ($f) => $f * min($freqB ?: [0]) >= 0));
            foreach ($as as $a) {
                foreach ($freqB as $b => $fb) {
                    if ($symmetric && strcmp($a, $b) >= 0) {
                        continue;
                    }
                    $c = $count[$a."\x1F".$b] ?? 0;
                    if ($c < self::MIN_COUNT && $freqA[$a] * $fb / $n >= self::NEG_EXPECTED) {
                        $rows[] = $row($a, $b, $c);
                    }
                }
            }
        }

        return $rows;
    }

    // ------------------------------------------------------------------ chấm điểm

    private function pairMap(): array
    {
        if ($this->pairs === null) {
            $this->pairs = [];
            ItemPair::query()->where('user_id', $this->user->id)->select(['level', 'a', 'b', 'score', 'count'])
                ->orderBy('id')->each(function (ItemPair $p) {
                    $this->pairs[$p->level."\x1F".$p->a."\x1F".$p->b] = [$p->score, $p->count];
                }, 2000);
        }

        return $this->pairs;
    }

    /** Tra 1 cặp (thứ tự a, b không quan trọng với cấp đối xứng). */
    private function lookup(string $level, string $a, string $b): ?array
    {
        $map = $this->pairMap();
        if (in_array($level, ['cat_shoe', 'color_shoe', 'cat_ctx'], true)) {
            return $map[$level."\x1F".$a."\x1F".$b] ?? null;
        }
        [$x, $y] = strcmp($a, $b) <= 0 ? [$a, $b] : [$b, $a];

        return $map[$level."\x1F".$x."\x1F".$y] ?? null;
    }

    /**
     * Chấm 1 bộ món. $items: mảng thuộc tính như outfit_items (slot, category, color, ...).
     * Trả về score 0..100, pairs (điểm từng cặp, thấp nhất trước), issues (vi phạm luật cứng).
     */
    public function score(array $items, ?string $shoe = null, array $context = []): array
    {
        $pairs = [];
        $sum = 0.0;
        $weight = 0.0;
        $m = count($items);
        for ($i = 0; $i < $m; $i++) {
            for ($j = $i + 1; $j < $m; $j++) {
                [$s, $w, $detail] = $this->pairScore($items[$i], $items[$j]);
                $sum += $s * $w;
                $weight += $w;
                $pairs[] = ['a' => $this->label($items[$i]), 'b' => $this->label($items[$j]), 'score' => round($s, 3), ...$detail];
            }
            if ($shoe) {
                $parts = [];
                foreach ([['cat_shoe', $items[$i]['category'] ?? null, 1.0], ['color_shoe', $items[$i]['color'] ?? null, 0.5]] as [$lv, $a, $w]) {
                    if ($a && ($hit = $this->lookup($lv, $a, $shoe))) {
                        $parts[] = [$hit[0], $w, $lv, $hit[1]];
                    }
                }
                if ($parts) {
                    $s = array_sum(array_map(fn ($p) => $p[0] * $p[1], $parts)) / array_sum(array_column($parts, 1));
                    $sum += $s * 0.8;
                    $weight += 0.8;
                    $pairs[] = ['a' => $this->label($items[$i]), 'b' => $shoe, 'score' => round($s, 3),
                        'level' => implode('+', array_column($parts, 2)), 'count' => max(array_column($parts, 3))];
                }
            }
            foreach ($context as $tag) {
                if (($cat = $items[$i]['category'] ?? null) && ($hit = $this->lookup('cat_ctx', $cat, $tag))) {
                    $sum += $hit[0] * 0.5;
                    $weight += 0.5;
                    $pairs[] = ['a' => $this->label($items[$i]), 'b' => $tag, 'score' => round($hit[0], 3), 'level' => 'cat_ctx', 'count' => $hit[1]];
                }
            }
        }
        $issues = self::hardIssues($items);
        // bộ ít cặp bằng chứng bị kéo về 0 (prior PRIOR_WEIGHT ở điểm 0) -> không ưu tiên bộ quá ít món
        $mean = $sum / ($weight + self::PRIOR_WEIGHT);
        $total = (int) round(max(0, min(100, 50 + 50 * $mean - 15 * count($issues))));
        usort($pairs, fn ($x, $y) => $x['score'] <=> $y['score']);

        return ['score' => $total, 'mean' => round($mean, 3), 'pairs' => $pairs, 'issues' => $issues];
    }

    /** Điểm 1 cặp món: cấu trúc (cấp chi tiết nhất có dữ liệu) + màu + chất liệu + hoạ tiết. */
    private function pairScore(array $x, array $y): array
    {
        $parts = [];
        $tx = self::tokens($x);
        $ty = self::tokens($y);
        foreach ([['style_full', 1.2], ['cat_color', 1.0], ['cat', 0.8]] as [$lv, $w]) {
            $hit = ($tx[$lv][0] ?? null) && ($ty[$lv][0] ?? null) ? $this->lookup($lv, $tx[$lv][0], $ty[$lv][0]) : null;
            if ($hit) {
                $parts[] = [$hit[0], $w, $lv, $hit[1]];
                break;
            }
        }
        foreach ([['color', 0.6], ['material', 0.4], ['pattern', 0.3]] as [$lv, $w]) {
            $scores = [];
            foreach ($tx[$lv] ?? [] as $a) {
                foreach ($ty[$lv] ?? [] as $b) {
                    if ($a === $b) {
                        $scores[] = [$lv === 'color' ? 0.1 : 0.0, 0]; // cùng màu / chất liệu: trung tính hơi dương
                    } elseif ($hit = $this->lookup($lv, $a, $b)) {
                        $scores[] = $hit;
                    }
                }
            }
            if ($scores) {
                $parts[] = [array_sum(array_column($scores, 0)) / count($scores), $w, $lv, max(array_column($scores, 1))];
            }
        }
        if (! $parts) {
            return [0.0, 0.3, ['level' => 'unknown', 'count' => 0]];
        }
        $w = array_sum(array_column($parts, 1));
        $s = array_sum(array_map(fn ($p) => $p[0] * $p[1], $parts)) / $w;

        return [$s, 1.0, ['level' => implode('+', array_column($parts, 2)), 'count' => max(array_column($parts, 3))]];
    }

    private function label(array $item): string
    {
        return implode(':', array_filter([$item['category'] ?? '?', $item['color'] ?? null, $item['material'] ?? null]));
    }

    /** Luật cấu trúc cứng (luật phối do người dùng duyệt nằm ở styling_rules, Claude tự áp khi viết outfit). */
    public static function hardIssues(array $items): array
    {
        $slots = array_count_values(array_map(fn ($i) => $i['slot'] ?? 'other', $items));
        $issues = [];
        if (($slots['dress'] ?? 0) > 0 && ($slots['bottom'] ?? 0) > 0) {
            $issues[] = 'Đầm / set liền đi cùng quần hoặc chân váy';
        }
        foreach (['dress' => 1, 'bottom' => 1, 'outer' => 1, 'shoes' => 1, 'socks' => 1, 'bag' => 1, 'top' => 2] as $slot => $max) {
            if (($slots[$slot] ?? 0) > $max) {
                $issues[] = "Quá nhiều món ở vị trí {$slot} ({$slots[$slot]})";
            }
        }
        if (($slots['dress'] ?? 0) === 0 && (($slots['top'] ?? 0) === 0 || ($slots['bottom'] ?? 0) === 0)) {
            $issues[] = 'Thiếu áo hoặc phần dưới (không có đầm)';
        }

        return $issues;
    }

    // ------------------------------------------------------------------ gợi ý

    /**
     * Gợi ý n bộ món cho đôi giày + ngữ cảnh. Ghép theo beam search trên các biến thể món đã có trong outfit_items.
     * $fixed: các món bắt buộc có; $excludeOutfitIds: không trả bộ gần trùng (>= 80% loại:màu) các outfit này;
     * mặc định so với MỌI outfit của tài khoản.
     */
    public function suggest(?string $shoe, array $context, array $fixed = [], int $n = 5, ?array $excludeOutfitIds = null): array
    {
        $variants = OutfitItem::query()->where('user_id', $this->user->id)
            ->whereIn('slot', ['top', 'outer', 'dress', 'bottom', 'socks', 'bag'])
            ->get()->groupBy(fn (OutfitItem $i) => implode('|', [$i->slot, $i->category, $i->color, $i->material, $i->fit, $i->length, $i->pattern]))
            ->map(fn ($g) => ['item' => collect($g->first()->toArray())->except(['id', 'outfit_id', 'position', 'created_at', 'updated_at'])->all(), 'freq' => $g->count()]);
        $bySlot = [];
        foreach ($variants as $v) {
            $bySlot[$v['item']['slot']][] = $v;
        }
        foreach ($bySlot as $slot => $list) {
            // ưu tiên món hợp giày + ngữ cảnh, rồi tần suất
            usort($list, fn ($x, $y) => $this->relevance($y['item'], $shoe, $context) <=> $this->relevance($x['item'], $shoe, $context) ?: $y['freq'] <=> $x['freq']);
            $bySlot[$slot] = array_slice($list, 0, 40);
        }

        $fixedSlots = array_count_values(array_map(fn ($i) => $i['slot'] ?? 'other', $fixed));
        $plans = [['dress', 'bag', 'outer?', 'socks?'], ['bottom', 'top', 'bag', 'outer?', 'socks?']];
        $results = [];
        foreach ($plans as $plan) {
            $beam = [[$fixed, 0]];
            foreach ($plan as $step) {
                $optional = str_ends_with($step, '?');
                $slot = rtrim($step, '?');
                if (($fixedSlots[$slot] ?? 0) > 0 || empty($bySlot[$slot])) {
                    continue; // đã có món cố định, hoặc kho chưa có món nào ở vị trí này
                }
                $next = [];
                foreach ($beam as [$chosen]) {
                    if ($optional) {
                        $next[] = [$chosen, $this->score($chosen, $shoe, $context)['mean']];
                    }
                    foreach ($bySlot[$slot] ?? [] as $v) {
                        $cand = [...$chosen, $v['item']];
                        $next[] = [$cand, $this->score($cand, $shoe, $context)['mean']];
                    }
                }
                usort($next, fn ($x, $y) => $y[1] <=> $x[1]);
                $beam = array_slice($next, 0, 30);
            }
            foreach ($beam as [$chosen]) {
                $results[] = $chosen;
            }
        }

        $existing = Outfit::query()->where('user_id', $this->user->id)
            ->when($excludeOutfitIds !== null, fn ($q) => $q->whereIn('id', $excludeOutfitIds))
            ->with('itemsList:id,outfit_id,category,color')->get()
            ->map(fn (Outfit $o) => $o->itemsList->map(fn ($i) => "{$i->category}:{$i->color}")->unique()->values()->all())
            ->filter()->all();
        $out = [];
        $seen = [];
        $mainUsed = [];
        $scored = array_map(fn ($items) => [$items, $this->score($items, $shoe, $context)], $results);
        usort($scored, fn ($x, $y) => $y[1]['score'] <=> $x[1]['score']);
        foreach ($scored as [$items, $score]) {
            $key = collect($items)->map(fn ($i) => ($i['category'] ?? '').':'.($i['color'] ?? ''))->sort()->implode('|');
            if (isset($seen[$key]) || $score['issues']) {
                continue;
            }
            $set = explode('|', $key);
            $dup = false;
            foreach ($existing as $other) {
                if (count(array_intersect($set, $other)) / max(1, max(count($set), count($other))) >= 0.8) {
                    $dup = true;
                    break;
                }
            }
            if ($dup) {
                continue;
            }
            $main = collect($items)->first(fn ($i) => in_array($i['slot'] ?? '', ['dress', 'bottom'], true));
            $mainKey = $main ? $main['category'].':'.($main['color'] ?? '') : '';
            if (($mainUsed[$mainKey] ?? 0) >= self::MAX_SAME_MAIN) {
                continue;
            }
            $mainUsed[$mainKey] = ($mainUsed[$mainKey] ?? 0) + 1;
            $seen[$key] = true;
            $out[] = ['items' => array_values($items), 'score' => $score['score'], 'pairs' => array_slice($score['pairs'], 0, 6)];
            if (count($out) >= $n) {
                break;
            }
        }

        return $out;
    }

    private function relevance(array $item, ?string $shoe, array $context): float
    {
        $s = 0.0;
        if ($shoe && ($hit = $this->lookup('cat_shoe', $item['category'], $shoe))) {
            $s += $hit[0];
        }
        if ($shoe && ! empty($item['color']) && ($hit = $this->lookup('color_shoe', $item['color'], $shoe))) {
            $s += 0.5 * $hit[0];
        }
        foreach ($context as $tag) {
            if ($hit = $this->lookup('cat_ctx', $item['category'], $tag)) {
                $s += 0.5 * $hit[0];
            }
        }

        return $s;
    }
}
