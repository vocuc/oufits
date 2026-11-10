<?php

namespace App\Http\Controllers;

use App\Models\Hashtag;
use App\Models\Outfit;
use Illuminate\Http\Request;

class OutfitController extends Controller
{
    public function index(Request $request)
    {
        // ?tags=thudong,giaycaogot  hoặc nhập "#thudong giaycaogot" vào ô tìm
        $raw = $request->query('tags', '');
        $selected = collect(preg_split('/[\s,]+/', (string) $raw))
            ->map(fn ($t) => Hashtag::normalize($t))
            ->filter()->unique()->values()->all();

        $outfits = Outfit::with('hashtags')
            ->withAllHashtags($selected)
            ->latest()
            ->get();

        $hashtags = Hashtag::withCount('outfits')
            ->having('outfits_count', '>', 0)
            ->orderByDesc('outfits_count')
            ->orderBy('name')
            ->get();

        return view('outfits.index', compact('outfits', 'hashtags', 'selected'));
    }

    public function show(Outfit $outfit)
    {
        $outfit->load('hashtags');

        return view('outfits.show', compact('outfit'));
    }

    public function create()
    {
        return view('outfits.form', ['outfit' => new Outfit, 'hashtagText' => '']);
    }

    public function store(Request $request)
    {
        $outfit = Outfit::create($this->validated($request));
        $outfit->syncHashtags($request->input('hashtags'));

        return redirect()->route('outfits.show', $outfit)->with('status', 'Đã lưu outfit.');
    }

    public function edit(Outfit $outfit)
    {
        return view('outfits.form', [
            'outfit' => $outfit,
            'hashtagText' => $outfit->hashtags->pluck('name')->implode(' '),
        ]);
    }

    public function update(Request $request, Outfit $outfit)
    {
        $outfit->update($this->validated($request));
        $outfit->syncHashtags($request->input('hashtags'));

        return redirect()->route('outfits.show', $outfit)->with('status', 'Đã cập nhật outfit.');
    }

    public function destroy(Outfit $outfit)
    {
        $outfit->delete();

        return redirect()->route('outfits.index')->with('status', 'Đã xóa outfit.');
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
            'prompt' => ['required', 'string'],
            'fix_prompt' => ['nullable', 'string'],
            'hashtags' => ['required', 'string', 'max:2000'],
        ], [
            'title.required' => 'Nhập tên outfit.',
            'prompt.required' => 'Nhập prompt.',
            'hashtags.required' => 'Nhập ít nhất một hashtag (vd. thudong vay giaycaogot).',
            'source_url.url' => 'Link nguồn không hợp lệ.',
        ]);

        unset($data['hashtags']);

        return $data;
    }
}
