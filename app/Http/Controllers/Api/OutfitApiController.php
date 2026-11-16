<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Hashtag;
use App\Models\ItemPair;
use App\Models\Outfit;
use App\Models\OutfitLayout;
use App\Support\OutfitWiper;
use App\Support\PromptComposer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OutfitApiController extends Controller
{
    private const DEFAULT_LIMIT = 50;

    private const MAX_LIMIT = 200;

    private const COVER_RULES = ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'];

    /**
     * Danh sách outfit.
     * Mặc định: outfit của mình (cả riêng tư) + toàn bộ outfit tài khoản khác chia sẻ cho mình + outfit công khai.
     * ?scope=mine            -> chỉ outfit của mình (dùng để kiểm tra trùng trước khi thêm).
     * ?owner=<user id>       -> toàn bộ outfit của tài khoản đó (cả riêng tư) nếu họ đã chia sẻ cho mình, không thì 404.
     *                           Danh sách tài khoản đã chia sẻ: GET /api/outfit-shares.
     * ?tags=thudong,giayboot -> chỉ outfit có ĐỦ các hashtag này.
     * ?limit=50&offset=0     -> phân trang (limit tối đa 200); meta.next_offset = null khi hết.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'scope' => ['nullable', 'in:all,mine'],
            'owner' => ['nullable', 'integer', 'min:1'],
            'tags' => ['nullable', 'string', 'max:2000'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_LIMIT],
            'offset' => ['nullable', 'integer', 'min:0'],
        ]);
        $limit = (int) $request->query('limit', self::DEFAULT_LIMIT);
        $offset = (int) $request->query('offset', 0);

        $user = $request->user();
        $tags = collect(preg_split('/[\s,]+/', (string) $request->query('tags', '')))
            ->map(fn ($t) => Hashtag::normalize($t))
            ->filter()->unique()->values()->all();

        if ($request->filled('owner')) {
            $ownerId = (int) $request->query('owner');
            abort_unless($user->canViewOutfitsOf($ownerId), 404, 'Tài khoản này chưa chia sẻ outfit cho bạn.');
            $query = Outfit::where('user_id', $ownerId);
        } else {
            $query = $request->query('scope') === 'mine' ? $user->outfits() : Outfit::visibleTo($user);
        }

        $query->withAllHashtags($tags);
        $total = (clone $query)->count();

        $outfits = $query->with(['hashtags', 'user:id,name'])
            ->orderByDesc('created_at')->orderByDesc('id')
            ->skip($offset)->take($limit)
            ->get()
            ->map(fn (Outfit $o) => $this->present($o));

        return response()->json([
            'data' => $outfits,
            'meta' => [
                'total' => $total,
                'limit' => $limit,
                'offset' => $offset,
                'next_offset' => $offset + $outfits->count() < $total ? $offset + $outfits->count() : null,
            ],
        ]);
    }

    /**
     * Tạo outfit mới.
     * hashtags: chuỗi "thudong #vay giaycaogot" hoặc mảng ["thudong", "vay"].
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'style' => ['nullable', 'string', 'max:255'],
            'source_title' => ['nullable', 'string', 'max:255'],
            'source_author' => ['nullable', 'string', 'max:255'],
            'source_url' => ['nullable', 'url', 'max:255'],
            'source_position' => ['nullable', 'string', 'max:255'],
            'items' => ['required', 'string'],
            'hashtags' => ['required'],
            'hashtags.*' => ['string', 'max:100'],
            'cover' => self::COVER_RULES,
            'is_public' => ['sometimes', 'boolean'],
        ]);

        $hashtags = $data['hashtags'];
        unset($data['hashtags'], $data['cover']);
        if ($request->has('is_public')) {
            $data['is_public'] = $request->boolean('is_public');
        }

        if (! is_string($hashtags) && ! is_array($hashtags)) {
            return response()->json([
                'message' => 'hashtags phải là chuỗi hoặc mảng.',
                'errors' => ['hashtags' => ['hashtags phải là chuỗi hoặc mảng.']],
            ], 422);
        }

        $outfit = DB::transaction(function () use ($request, $data, $hashtags) {
            $outfit = $request->user()->outfits()->create($data);
            $outfit->syncHashtags($hashtags);

            return $outfit;
        });

        if ($request->hasFile('cover')) {
            $outfit->replaceCover($request->file('cover'));
        }

        return response()->json(['data' => $this->present($outfit->load('hashtags'))], 201);
    }

    /** Gắn / thay ảnh bìa cho outfit đã có (multipart, trường "cover"). */
    public function cover(Request $request, Outfit $outfit): JsonResponse
    {
        abort_unless($outfit->user_id === $request->user()->id, 404);

        $request->validate(['cover' => ['required', ...array_slice(self::COVER_RULES, 1)]]);
        $outfit->replaceCover($request->file('cover'));

        return response()->json(['data' => $this->present($outfit->load('hashtags'))]);
    }

    /**
     * Tài khoản chia sẻ outfit qua lại: shared_with = mình chia sẻ cho ai; shared_owners = ai chia sẻ cho mình
     * (dùng id làm ?owner= ở GET /api/outfits). Thêm / bỏ chia sẻ làm trên web (Cài đặt).
     */
    public function shares(Request $request): JsonResponse
    {
        $user = $request->user();
        $list = fn ($q) => $q->get(['users.id', 'users.name', 'users.email'])
            ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name, 'email' => $u->email])->values();

        return response()->json(['data' => [
            'shared_with' => $list($user->sharedWith()),
            'shared_owners' => $list($user->sharedOwners()),
        ]]);
    }

    /** Tải ảnh bìa (ảnh gốc) của outfit của mình hoặc được chia sẻ cho mình: worker phối đồ gửi ảnh này cho ChatGPT làm ảnh mẫu outfit. */
    public function coverFile(Request $request, Outfit $outfit)
    {
        abort_unless($outfit->cover_path && $request->user()->canViewOutfitsOf((int) $outfit->user_id), 404);
        $disk = Outfit::coverDisk();
        if ($disk->providesTemporaryUrls()) {
            return redirect()->away($disk->temporaryUrl($outfit->cover_path, now()->addMinutes(30)));
        }
        abort_unless($disk->exists($outfit->cover_path), 404);

        return $disk->response($outfit->cover_path);
    }

    /** Xoá 1 outfit của mình (kèm món đồ, ảnh Thư viện ảnh, điểm chấm, ảnh bìa trên disk). */
    public function destroy(Request $request, Outfit $outfit): JsonResponse
    {
        abort_unless($outfit->user_id === $request->user()->id, 404);

        return response()->json(['data' => OutfitWiper::wipe($request->user(), [$outfit->id])]);
    }

    /**
     * Xoá HẾT outfit của mình (2026-11-16, chủ shop muốn làm lại từ đầu).
     * Bắt buộc ?confirm=xoa-het-outfit để tránh gọi nhầm. Xoá luôn món đồ, ảnh Thư viện ảnh,
     * điểm chấm, cặp món đã học (item_pairs); giữ quy tắc, kiến thức phối đồ, layout, mẫu bìa.
     */
    public function destroyAll(Request $request): JsonResponse
    {
        if ($request->query('confirm') !== 'xoa-het-outfit') {
            throw ValidationException::withMessages(['confirm' => 'Thêm ?confirm=xoa-het-outfit để xoá hết outfit.']);
        }
        $user = $request->user();
        $result = OutfitWiper::wipe($user, $user->outfits()->pluck('id')->all());
        $result['item_pairs'] = ItemPair::where('user_id', $user->id)->delete();

        return response()->json(['data' => $result]);
    }

    /**
     * Thêm / bớt hashtag của outfit (chỉ outfit của mình). Hashtag khác giữ nguyên.
     * { "add": ["henho", "dichoi"], "remove": ["vay"] } — mỗi phần là mảng hoặc chuỗi "a, #b c".
     * Tag có trong cả add và remove -> remove thắng.
     */
    public function hashtags(Request $request, Outfit $outfit): JsonResponse
    {
        abort_unless($outfit->user_id === $request->user()->id, 404);

        $request->validate([
            'add' => ['nullable'],
            'remove' => ['nullable'],
        ]);
        $add = $this->tagList($request->input('add'), 'add');
        $remove = $this->tagList($request->input('remove'), 'remove');
        if ($add === [] && $remove === []) {
            throw ValidationException::withMessages(['add' => 'Cần ít nhất một hashtag trong add hoặc remove.']);
        }

        $current = $outfit->hashtags()->pluck('name')->all();
        $names = array_values(array_diff(array_unique([...$current, ...$add]), $remove));
        DB::transaction(fn () => $outfit->syncHashtags($names));

        return response()->json(['data' => $this->present($outfit->load('hashtags'))]);
    }

    /** @return list<string> hashtag đã chuẩn hoá */
    private function tagList(mixed $input, string $field): array
    {
        if ($input === null || $input === '') {
            return [];
        }
        if (! is_string($input) && ! is_array($input)) {
            throw ValidationException::withMessages([$field => "$field phải là chuỗi hoặc mảng."]);
        }

        return collect(is_array($input) ? $input : preg_split('/[\s,]+/', $input))
            ->map(fn ($t) => is_string($t) ? Hashtag::normalize($t) : '')
            ->filter()->unique()->values()->all();
    }

    /**
     * Prompt hoàn chỉnh cho các outfit (giống trang /compose): phần dùng chung của tài khoản + outfit theo đúng thứ tự ids,
     * kèm câu sửa nhanh của tài khoản. Dùng cho skill tạo lookbook (mỗi lượt ChatGPT gọi với 1 id).
     * Id không thuộc tài khoản / không tồn tại -> 422 (không lặng lẽ bỏ qua, để người gọi biết prompt thiếu outfit).
     */
    public function compose(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct'],
            'layout' => ['nullable', 'string', 'max:40'],
            'hook' => ['nullable', 'string', 'max:300'],
        ]);
        $ids = collect($data['ids'])->map(fn ($id) => (int) $id)->values();

        $user = $request->user();
        // Prompt khung ảnh lấy từ layout ĐÃ DUYỆT (Mẫu ảnh outfit); không còn prompt dùng chung.
        $layout = OutfitLayout::pick($user, $data['layout'] ?? null);
        if (! $layout) {
            throw ValidationException::withMessages(['layout' => isset($data['layout'])
                ? "Layout {$data['layout']} chưa được duyệt hoặc chưa có prompt."
                : OutfitLayout::NO_APPROVED]);
        }
        $outfits = $user->outfits()->whereIn('id', $ids)->get()->keyBy('id');

        $missing = $ids->reject(fn (int $id) => $outfits->has($id))->values();
        if ($missing->isNotEmpty()) {
            throw ValidationException::withMessages([
                'ids' => 'Không tìm thấy outfit: '.$missing->implode(', ').'.',
            ]);
        }

        $ordered = $ids->map(fn (int $id) => $outfits->get($id));

        return response()->json(['data' => [
            'outfit_ids' => $ids,
            'layout' => $layout->code,
            'prompt' => PromptComposer::compose($ordered, $layout->prompt, $data['hook'] ?? null),
            'fix' => $layout->fixFor($user),
        ]]);
    }

    private function present(Outfit $outfit): array
    {
        return [
            'id' => $outfit->id,
            'title' => $outfit->title,
            'style' => $outfit->style,
            'source_title' => $outfit->source_title,
            'source_author' => $outfit->source_author,
            'source_url' => $outfit->source_url,
            'source_position' => $outfit->source_position,
            'items' => $outfit->items,
            'hashtags' => $outfit->hashtags->pluck('name')->values(),
            'has_cover' => (bool) $outfit->cover_path,
            'is_public' => $outfit->is_public,
            'is_mine' => $outfit->isOwnedBy(request()->user()),
            'owner' => $outfit->user?->name,
            'url' => route('outfits.show', $outfit),
            'created_at' => $outfit->created_at?->toIso8601String(),
        ];
    }
}
