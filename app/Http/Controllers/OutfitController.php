<?php

namespace App\Http\Controllers;

use App\Models\Hashtag;
use App\Models\Outfit;
use App\Support\PromptComposer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/** Mọi thao tác chỉ trên outfit của tài khoản đang đăng nhập. */
class OutfitController extends Controller
{
    private const FIRST_PAGE = 30;

    private const MORE_PAGE = 20;

    public function index(Request $request)
    {
        $user = $request->user();

        // ?tags=thudong,giaycaogot  hoặc nhập "#thudong giaycaogot" vào ô tìm
        $raw = $request->query('tags', '');
        $selected = collect(preg_split('/[\s,]+/', (string) $raw))
            ->map(fn ($t) => Hashtag::normalize($t))
            ->filter()->unique()->values()->all();

        // Trang đầu 30 outfit mới nhất, cuộn xuống tải thêm 20 mỗi lần (?offset=)
        $offset = max(0, (int) $request->query('offset', 0));
        $limit = $offset === 0 ? self::FIRST_PAGE : self::MORE_PAGE;
        $query = $user->outfits()->withAllHashtags($selected);
        $total = (clone $query)->count();
        $outfits = $query->with('hashtags')->withCount('renders')
            ->orderByDesc('created_at')->orderByDesc('id')
            ->skip($offset)->take($limit)
            ->get();
        $nextOffset = $offset + $outfits->count() < $total ? $offset + $outfits->count() : null;

        if ($request->wantsJson()) {
            return response()->json([
                'html' => view('outfits._cards', compact('outfits', 'selected'))->render(),
                'next_offset' => $nextOffset,
            ]);
        }

        // Chỉ đếm hashtag trên outfit của tài khoản này
        $mine = fn (Builder $q) => $q->where('user_id', $user->id);
        $hashtags = Hashtag::withCount(['outfits' => $mine])
            ->whereHas('outfits', $mine)
            ->orderByDesc('outfits_count')
            ->orderBy('name')
            ->get();

        return view('outfits.index', compact('outfits', 'hashtags', 'selected', 'total', 'nextOffset'));
    }

    /** Ghép prompt dùng chung của tài khoản + các outfit đã chọn: /compose?ids[]=1&ids[]=2 */
    public function compose(Request $request)
    {
        $user = $request->user();
        $ids = collect((array) $request->query('ids', []))
            ->map(fn ($id) => (int) $id)->filter()->unique()->values();

        // Outfit của mình + outfit công khai của người khác
        $outfits = Outfit::whereIn('id', $ids)
            ->where(fn (Builder $q) => $q->where('user_id', $user->id)->orWhere('is_public', true))
            ->get()
            ->sortBy(fn (Outfit $o) => $ids->search($o->id))->values();

        return view('outfits.compose', [
            'outfits' => $outfits,
            'prompt' => $outfits->isEmpty() ? '' : PromptComposer::compose($outfits, $user->commonPrompt()),
            'common' => $user->commonPrompt(),
            'fix' => $user->fixPrompt(),
        ]);
    }

    public function show(Request $request, Outfit $outfit)
    {
        $this->authorizeView($request, $outfit);
        $outfit->load('hashtags')->loadCount('renders');

        return view('outfits.show', compact('outfit'));
    }

    public function create()
    {
        return view('outfits.form', ['outfit' => new Outfit, 'hashtagText' => '']);
    }

    public function store(Request $request)
    {
        $outfit = $request->user()->outfits()->create($this->validated($request));
        $outfit->syncHashtags($request->input('hashtags'));
        $this->saveCover($request, $outfit);

        return redirect()->route('outfits.show', $outfit)->with('status', 'Đã lưu outfit.');
    }

    public function edit(Request $request, Outfit $outfit)
    {
        $this->authorizeOwner($request, $outfit);

        return view('outfits.form', [
            'outfit' => $outfit,
            'hashtagText' => $outfit->hashtags->pluck('name')->implode(' '),
        ]);
    }

    public function update(Request $request, Outfit $outfit)
    {
        $this->authorizeOwner($request, $outfit);
        $outfit->update($this->validated($request));
        $outfit->syncHashtags($request->input('hashtags'));
        $this->saveCover($request, $outfit);

        return redirect()->route('outfits.show', $outfit)->with('status', 'Đã cập nhật outfit.');
    }

    public function destroy(Request $request, Outfit $outfit)
    {
        $this->authorizeOwner($request, $outfit);
        $outfit->delete();

        return redirect()->route('outfits.index')->with('status', 'Đã xóa outfit.');
    }

    /** Ảnh bìa (chủ outfit, hoặc ai đăng nhập nếu outfit công khai). */
    public function cover(Request $request, Outfit $outfit)
    {
        $this->authorizeView($request, $outfit);
        abort_unless($outfit->cover_path, 404);
        $disk = Outfit::coverDisk();

        // S3: chuyển hướng sang link ký tạm thời, trình duyệt tải ảnh thẳng từ S3.
        if ($disk->providesTemporaryUrls()) {
            return redirect()->away($disk->temporaryUrl($outfit->cover_path, now()->addHour()))
                ->header('Cache-Control', 'private, max-age=3000');
        }

        abort_unless($disk->exists($outfit->cover_path), 404);

        return $disk->response($outfit->cover_path, null, [
            'Cache-Control' => 'private, max-age=31536000, immutable',
        ]);
    }

    private function saveCover(Request $request, Outfit $outfit): void
    {
        if ($request->hasFile('cover')) {
            $outfit->replaceCover($request->file('cover'));
        } elseif ($request->boolean('remove_cover')) {
            $outfit->removeCover();
        }
    }

    /** Chỉ chủ outfit được sửa / xóa; outfit riêng tư của người khác coi như không tồn tại. */
    private function authorizeOwner(Request $request, Outfit $outfit): void
    {
        $this->authorizeView($request, $outfit);
        abort_unless($outfit->isOwnedBy($request->user()), 403);
    }

    private function authorizeView(Request $request, Outfit $outfit): void
    {
        abort_unless($outfit->isVisibleTo($request->user()), 404);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'style' => ['nullable', 'string', 'max:255'],
            'source_title' => ['nullable', 'string', 'max:255'],
            'source_author' => ['nullable', 'string', 'max:255'],
            'source_url' => ['nullable', 'url', 'max:255'],
            'source_position' => ['nullable', 'string', 'max:255'],
            'items' => ['required', 'string'],
            'hashtags' => ['required', 'string', 'max:2000'],
            'cover' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
            'remove_cover' => ['nullable', 'boolean'],
            'is_public' => ['sometimes', 'boolean'],
        ], [
            'title.required' => 'Nhập tên outfit.',
            'items.required' => 'Nhập các item / cách phối đồ.',
            'hashtags.required' => 'Nhập ít nhất một hashtag (vd. thudong vay giaycaogot).',
            'source_url.url' => 'Link nguồn không hợp lệ.',
            'cover.image' => 'Ảnh bìa phải là file ảnh.',
            'cover.mimes' => 'Ảnh bìa chỉ nhận JPG, PNG, WEBP.',
            'cover.max' => 'Ảnh bìa tối đa 8 MB.',
            'cover.uploaded' => 'Tải ảnh bìa lên thất bại (file quá lớn?).',
        ]);

        unset($data['hashtags'], $data['cover'], $data['remove_cover']);
        if ($request->has('is_public')) {
            $data['is_public'] = $request->boolean('is_public');
        }

        return $data;
    }
}
