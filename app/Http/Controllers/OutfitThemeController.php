<?php

namespace App\Http\Controllers;

use App\Models\OutfitTheme;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Trang "Chủ đề": chủ shop thêm / sửa / duyệt / bỏ / xoá chủ đề lô phối đồ (hashtag + các câu hook). */
class OutfitThemeController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status');
        $status = array_key_exists((string) $status, OutfitTheme::STATUSES) ? $status : null;
        $all = $request->user()->outfitThemes();
        $counts = (clone $all)->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');
        $themes = $all->when($status, fn ($q) => $q->where('status', $status))
            ->orderBy('sort')->orderBy('id')->get();

        return view('themes.index', [
            'themes' => $themes, 'status' => $status, 'counts' => $counts,
            'statuses' => OutfitTheme::STATUSES, 'seasons' => OutfitTheme::SEASONS,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['sort'] = (int) $request->user()->outfitThemes()->max('sort') + 1;
        $theme = $request->user()->outfitThemes()->create($data);

        return $this->back($theme, 'Đã thêm chủ đề (chờ duyệt).');
    }

    public function update(Request $request, OutfitTheme $theme)
    {
        $this->own($request, $theme);
        $data = $this->validated($request, $theme);
        $status = $request->input('status');
        if ($status !== null && $status !== '') {
            abort_unless(array_key_exists($status, OutfitTheme::STATUSES), 422);
            $data['status'] = $status;
            $data['reviewed_at'] = now();
        }
        $theme->update($data);

        return $this->back($theme, 'Đã lưu chủ đề.');
    }

    public function destroy(Request $request, OutfitTheme $theme)
    {
        $this->own($request, $theme);
        $theme->delete();

        return redirect()->route('themes.index')->with('status', 'Đã xoá chủ đề.');
    }

    private function validated(Request $request, ?OutfitTheme $theme = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255',
                Rule::unique('outfit_themes', 'name')->where('user_id', $request->user()->id)->ignore($theme?->id)],
            'tags' => ['required', 'string', 'max:1000'],
            'hooks' => ['required', 'string', 'max:20000'],
            'season' => ['nullable', Rule::in(array_keys(OutfitTheme::SEASONS))],
            'note' => ['nullable', 'string', 'max:5000'],
        ]);
        $data['name'] = trim($data['name']);
        $data['tags'] = OutfitTheme::normalizeTags($data['tags']);
        $data['hooks'] = OutfitTheme::normalizeHooks($data['hooks']);
        $data['season'] = ($data['season'] ?? null) ?: null;
        if ($data['tags'] === '' || $data['hooks'] === '') {
            throw ValidationException::withMessages(['tags' => 'Cần ít nhất 1 hashtag và 1 câu hook.']);
        }

        return $data;
    }

    private function own(Request $request, OutfitTheme $theme): void
    {
        abort_unless($theme->user_id === $request->user()->id, 404);
    }

    private function back(OutfitTheme $theme, string $msg)
    {
        return redirect()->to(route('themes.index').'#chu-de-'.$theme->id)->with('status', $msg);
    }
}
