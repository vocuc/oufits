<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ItemPair;
use App\Models\Outfit;
use App\Models\OutfitItem;
use App\Support\StylingEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Món đồ + độ hợp (D:\FlowKit\a51-skills\outfit-video\thiet-ke-item.txt). Chỉ Claude dùng qua API.
 *   PUT  /api/outfits/{outfit}/items   { items: [...] }  thay toàn bộ món của outfit
 *   GET  /api/outfit-items?category=&color=&slot=&outfit_id=&missing=1
 *   POST /api/styling/learn
 *   GET  /api/item-pairs?level=&a=&b=&min_score=&max_score=&limit=
 *   POST /api/styling/score    { items: [...], shoe?, context?: [dichoi, thudong...] }
 *   POST /api/styling/suggest  { shoe?, context?, fixed_items?, n?, exclude_outfit_ids? }
 */
class StylingItemApiController extends Controller
{
    private const SLUG = 'regex:/^[a-z0-9_-]+$/';

    private function itemRules(string $prefix): array
    {
        $rules = [
            "$prefix.slot" => ['required', Rule::in(OutfitItem::SLOTS)],
            "$prefix.category" => ['required', 'string', 'max:60', self::SLUG],
            "$prefix.details" => ['nullable', 'array', 'max:20'],
            "$prefix.details.*" => ['string', 'max:60', self::SLUG],
            "$prefix.wear" => ['nullable', 'array', 'max:10'],
            "$prefix.wear.*" => ['string', 'max:60', self::SLUG],
            "$prefix.raw" => ['nullable', 'string', 'max:2000'],
        ];
        foreach (array_diff(OutfitItem::ATTRS, ['category']) as $attr) {
            $rules["$prefix.$attr"] = ['nullable', 'string', 'max:40', self::SLUG];
        }

        return $rules;
    }

    private static function clean(array $item): array
    {
        return collect($item)->only(['slot', ...OutfitItem::ATTRS, 'details', 'wear', 'raw'])->all();
    }

    public function putItems(Request $request, Outfit $outfit): JsonResponse
    {
        abort_unless($outfit->user_id === $request->user()->id, 404);
        $data = $request->validate(['items' => ['present', 'array', 'max:30'], ...$this->itemRules('items.*')]);

        DB::transaction(function () use ($outfit, $data) {
            $outfit->itemsList()->delete();
            foreach (array_values($data['items']) as $i => $item) {
                $outfit->itemsList()->create([...self::clean($item), 'user_id' => $outfit->user_id, 'position' => $i]);
            }
        });

        return response()->json(['data' => $outfit->itemsList()->get()]);
    }

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'category' => ['nullable', 'string', 'max:60'],
            'color' => ['nullable', 'string', 'max:40'],
            'slot' => ['nullable', Rule::in(OutfitItem::SLOTS)],
            'outfit_id' => ['nullable', 'integer'],
            'missing' => ['nullable', 'boolean'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'offset' => ['nullable', 'integer', 'min:0'],
        ]);
        $user = $request->user();
        if ($request->boolean('missing')) {
            $ids = $user->outfits()->whereDoesntHave('itemsList')->orderBy('id')->pluck('id');

            return response()->json(['data' => $ids]);
        }
        $query = $user->outfitItems();
        foreach (['category', 'color', 'slot', 'outfit_id'] as $f) {
            if (isset($data[$f])) {
                $query->where($f, $data[$f]);
            }
        }
        $total = (clone $query)->count();
        $limit = (int) ($data['limit'] ?? 200);
        $offset = (int) ($data['offset'] ?? 0);
        $items = $query->orderBy('outfit_id')->orderBy('position')->skip($offset)->take($limit)->get();

        return response()->json(['data' => $items, 'meta' => ['total' => $total,
            'next_offset' => $offset + $items->count() < $total ? $offset + $items->count() : null]]);
    }

    public function learn(Request $request): JsonResponse
    {
        @set_time_limit(300);

        return response()->json(['data' => (new StylingEngine($request->user()))->learn()]);
    }

    public function pairs(Request $request): JsonResponse
    {
        $data = $request->validate([
            'level' => ['nullable', Rule::in(ItemPair::LEVELS)],
            'a' => ['nullable', 'string', 'max:160'],
            'b' => ['nullable', 'string', 'max:160'],
            'min_score' => ['nullable', 'numeric', 'min:-1', 'max:1'],
            'max_score' => ['nullable', 'numeric', 'min:-1', 'max:1'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:500'],
        ]);
        $query = ItemPair::query()->where('user_id', $request->user()->id);
        if (isset($data['level'])) {
            $query->where('level', $data['level']);
        }
        // a / b khớp ở cả 2 phía (cặp đối xứng lưu a <= b)
        foreach (['a', 'b'] as $f) {
            if (isset($data[$f])) {
                $v = $data[$f];
                $query->where(fn ($q) => $q->where('a', $v)->orWhere('b', $v));
            }
        }
        if (isset($data['min_score'])) {
            $query->where('score', '>=', $data['min_score']);
        }
        if (isset($data['max_score'])) {
            $query->where('score', '<=', $data['max_score']);
        }
        $order = isset($data['max_score']) && ! isset($data['min_score']) ? 'asc' : 'desc';

        return response()->json(['data' => $query->orderBy('score', $order)->orderByDesc('count')
            ->limit((int) ($data['limit'] ?? 100))->get()]);
    }

    private function contextRules(): array
    {
        return [
            'shoe' => ['nullable', 'string', 'max:60', self::SLUG],
            'context' => ['nullable', 'array', 'max:5'],
            'context.*' => ['string', Rule::in(StylingEngine::CONTEXT_TAGS)],
        ];
    }

    public function score(Request $request): JsonResponse
    {
        $data = $request->validate(['items' => ['required', 'array', 'min:1', 'max:15'], ...$this->itemRules('items.*'), ...$this->contextRules()]);
        $items = array_map(fn ($i) => self::clean($i), array_values($data['items']));

        return response()->json(['data' => (new StylingEngine($request->user()))->score($items, $data['shoe'] ?? null, $data['context'] ?? [])]);
    }

    public function suggest(Request $request): JsonResponse
    {
        $data = $request->validate([
            ...$this->contextRules(),
            'fixed_items' => ['nullable', 'array', 'max:6'], ...$this->itemRules('fixed_items.*'),
            'n' => ['nullable', 'integer', 'min:1', 'max:20'],
            'exclude_outfit_ids' => ['nullable', 'array'],
            'exclude_outfit_ids.*' => ['integer'],
        ]);
        @set_time_limit(120);
        $fixed = array_map(fn ($i) => self::clean($i), array_values($data['fixed_items'] ?? []));

        return response()->json(['data' => (new StylingEngine($request->user()))->suggest(
            $data['shoe'] ?? null, $data['context'] ?? [], $fixed, (int) ($data['n'] ?? 5), $data['exclude_outfit_ids'] ?? null,
        )]);
    }
}
