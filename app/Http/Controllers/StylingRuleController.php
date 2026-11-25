<?php

namespace App\Http\Controllers;

use App\Models\Hashtag;
use App\Models\Outfit;
use App\Models\StylingRule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Trang "Luật cấm phối" (người dùng chốt 2026-11-25): luật "tránh" của styling_rules. Luật ĐÃ DUYỆT có loại giày + khía
 * cạnh hashtag được API outfit áp dụng (?rules=1): bỏ outfit có giày đó kèm món bị cấm, và lô theo sản phẩm (&shoe=) bỏ
 * outfit có món bị cấm với giày sẽ vẽ thay. Người dùng thêm luật (nguồn user, duyệt sẵn), duyệt / bỏ duyệt luật Claude soạn.
 */
class StylingRuleController extends Controller
{
    public const DIMENSION_LABELS = [
        'outer' => 'Áo khoác / áo ngoài',
        'bottom' => 'Phần dưới',
        'style' => 'Phong cách',
        'color' => 'Màu',
        'socks' => 'Tất',
        'season' => 'Mùa',
    ];

    private const MAX_PAIRS = 200;

    public function index(Request $request)
    {
        $user = $request->user();
        $rules = $user->stylingRules()->where('relation', 'avoid')
            ->orderByDesc('is_confirmed')->orderByRaw('shoe_type IS NULL')->orderBy('shoe_type')->orderBy('dimension')->orderBy('value')->get();
        // Số outfit đang tự vi phạm từng luật (có cả giày + món bị cấm): để chủ shop biết luật lọc mất bao nhiêu outfit
        $violations = $rules->mapWithKeys(fn (StylingRule $r) => [$r->id => $this->enforceable($r)
            ? $user->outfits()->withAllHashtags([Hashtag::normalize($r->shoe_type), Hashtag::normalize($r->value)])->count()
            : null]);

        return view('styling-rules.index', [
            'rules' => $rules, 'violations' => $violations, 'dimensions' => self::DIMENSION_LABELS,
        ]);
    }

    /** { shoes: "depquai depkep", dimension, values: "aomangto aokhoaclong", note } -> mỗi cặp giày x món 1 luật đã duyệt. */
    public function store(Request $request)
    {
        $data = $request->validate([
            'shoes' => ['required', 'string', 'max:1000'],
            'dimension' => ['required', Rule::in(array_keys(self::DIMENSION_LABELS))],
            'values' => ['required', 'string', 'max:1000'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);
        $shoes = $this->tags($data['shoes']);
        $values = $this->tags($data['values']);
        if (! $shoes || ! $values) {
            throw ValidationException::withMessages(['shoes' => 'Cần ít nhất 1 hashtag giày và 1 hashtag món bị cấm.']);
        }
        if (count($shoes) * count($values) > self::MAX_PAIRS) {
            throw ValidationException::withMessages(['values' => 'Tối đa '.self::MAX_PAIRS.' cặp giày x món mỗi lần.']);
        }

        $user = $request->user();
        DB::transaction(function () use ($user, $shoes, $values, $data) {
            foreach ($shoes as $shoe) {
                foreach ($values as $value) {
                    $user->stylingRules()->updateOrCreate(
                        ['shoe_key' => $shoe, 'dimension' => $data['dimension'], 'value' => $value, 'source' => 'user'],
                        ['shoe_type' => $shoe, 'relation' => 'avoid', 'score' => 1, 'note' => $data['note'] ?? null, 'is_confirmed' => true],
                    );
                }
            }
        });

        return redirect()->route('styling-rules.index')
            ->with('status', 'Đã thêm '.count($shoes) * count($values).' luật cấm (đã duyệt, có hiệu lực ngay).');
    }

    /** Duyệt / bỏ duyệt (luật Claude soạn chỉ có hiệu lực khi đã duyệt). */
    public function update(Request $request, StylingRule $rule)
    {
        $this->own($request, $rule);
        $data = $request->validate(['is_confirmed' => ['required', 'boolean']]);
        $rule->update(['is_confirmed' => (bool) $data['is_confirmed']]);

        return redirect()->to(route('styling-rules.index').'#luat-'.$rule->id)
            ->with('status', $rule->is_confirmed ? 'Đã duyệt luật.' : 'Đã bỏ duyệt luật (không còn lọc outfit).');
    }

    /** Chỉ xoá luật người dùng tự thêm; luật Claude / thống kê thì bỏ duyệt. */
    public function destroy(Request $request, StylingRule $rule)
    {
        $this->own($request, $rule);
        abort_unless($rule->source === 'user', 403, 'Chỉ xoá được luật bạn tự thêm; luật khác hãy bỏ duyệt.');
        $rule->delete();

        return redirect()->route('styling-rules.index')->with('status', 'Đã xoá luật.');
    }

    /** Luật API áp dụng được: có loại giày + khía cạnh là hashtag. */
    private function enforceable(StylingRule $rule): bool
    {
        return $rule->shoe_type !== null && in_array($rule->dimension, StylingRule::HASHTAG_DIMENSIONS, true);
    }

    /** @return string[] */
    private function tags(string $input): array
    {
        return collect(preg_split('/[\s,]+/', $input))->map(fn ($t) => Hashtag::normalize((string) $t))
            ->filter()->unique()->values()->all();
    }

    private function own(Request $request, StylingRule $rule): void
    {
        abort_unless($rule->user_id === $request->user()->id, 404);
    }
}
