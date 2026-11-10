<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Outfit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OutfitApiController extends Controller
{
    private const COVER_RULES = ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'];

    /** Danh sách outfit gọn (để kiểm tra trùng trước khi thêm). */
    public function index(Request $request): JsonResponse
    {
        $outfits = $request->user()->outfits()->with('hashtags')->latest()->get()
            ->map(fn (Outfit $o) => $this->present($o));

        return response()->json(['data' => $outfits]);
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
        ]);

        $hashtags = $data['hashtags'];
        unset($data['hashtags'], $data['cover']);

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
            'url' => route('outfits.show', $outfit),
            'created_at' => $outfit->created_at?->toIso8601String(),
        ];
    }
}
