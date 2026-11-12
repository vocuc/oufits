<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Hashtag;
use App\Models\Outfit;
use App\Support\PromptComposer;
use Illuminate\Database\Eloquent\Builder;
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
     * Mặc định: outfit của mình (cả riêng tư) + outfit công khai của người khác.
     * ?scope=mine            -> chỉ outfit của mình (dùng để kiểm tra trùng trước khi thêm).
     * ?tags=thudong,giayboot -> chỉ outfit có ĐỦ các hashtag này.
     * ?limit=50&offset=0     -> phân trang (limit tối đa 200); meta.next_offset = null khi hết.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'scope' => ['nullable', 'in:all,mine'],
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

        $query = $request->query('scope') === 'mine'
            ? $user->outfits()
            : Outfit::where(fn (Builder $q) => $q->where('user_id', $user->id)->orWhere('is_public', true));

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
     * Prompt hoàn chỉnh cho các outfit (giống trang /compose): phần dùng chung của tài khoản + outfit theo đúng thứ tự ids,
     * kèm câu sửa nhanh của tài khoản. Dùng cho skill tạo lookbook (mỗi lượt ChatGPT gọi với 1 id).
     * Id không thuộc tài khoản / không tồn tại -> 422 (không lặng lẽ bỏ qua, để người gọi biết prompt thiếu outfit).
     */
    public function compose(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct'],
        ]);
        $ids = collect($data['ids'])->map(fn ($id) => (int) $id)->values();

        $user = $request->user();
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
            'prompt' => PromptComposer::compose($ordered, $user->commonPrompt()),
            'fix' => $user->fixPrompt(),
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
