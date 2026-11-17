<?php

namespace App\Http\Controllers;

use App\Models\OutfitTheme;
use App\Models\OutfitThemeHook;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Trang "Chủ đề": chủ đề chỉ gom nhóm (tên + ghi chú); mỗi hook có hashtag riêng + duyệt riêng
 * (thêm / sửa câu + hashtag / duyệt / bỏ / xoá từng hook).
 */
class OutfitThemeController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status');
        $status = array_key_exists((string) $status, OutfitThemeHook::STATUSES) ? $status : null;
        $themes = $request->user()->outfitThemes()->with('hooks.theme')->orderBy('sort')->orderBy('id')->get();
        $counts = $themes->flatMap->hooks->countBy('status');

        return view('themes.index', [
            'themes' => $themes, 'status' => $status, 'counts' => $counts, 'statuses' => OutfitThemeHook::STATUSES,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->themeData($request);
        $data['sort'] = (int) $request->user()->outfitThemes()->max('sort') + 1;
        $theme = $request->user()->outfitThemes()->create($data);

        return $this->back('chu-de-'.$theme->id, 'Đã thêm chủ đề. Thêm hook cho chủ đề bên dưới.');
    }

    public function update(Request $request, OutfitTheme $theme)
    {
        $this->own($request, $theme);
        $theme->update($this->themeData($request, $theme));

        return $this->back('chu-de-'.$theme->id, 'Đã lưu chủ đề.');
    }

    public function destroy(Request $request, OutfitTheme $theme)
    {
        $this->own($request, $theme);
        $theme->delete();

        return redirect()->route('themes.index')->with('status', 'Đã xoá chủ đề và các hook của nó.');
    }

    public function storeHook(Request $request, OutfitTheme $theme)
    {
        $this->own($request, $theme);
        $data = $this->hookData($request);
        if ($theme->hooks()->where('body', $data['body'])->exists()) {
            throw ValidationException::withMessages(['body' => 'Chủ đề đã có câu hook này.']);
        }
        $hook = $theme->hooks()->create($data + ['sort' => (int) $theme->hooks()->max('sort') + 1]);

        return $this->back('hook-'.$hook->id, 'Đã thêm hook (chờ duyệt).');
    }

    public function updateHook(Request $request, OutfitThemeHook $hook)
    {
        $this->ownHook($request, $hook);
        $data = $this->hookData($request);
        if ($hook->theme->hooks()->where('body', $data['body'])->whereKeyNot($hook->id)->exists()) {
            throw ValidationException::withMessages(['body' => 'Chủ đề đã có câu hook này.']);
        }
        $status = $request->input('status');
        if ($status !== null && $status !== '') {
            abort_unless(array_key_exists($status, OutfitThemeHook::STATUSES), 422);
            $data['status'] = $status;
            $data['reviewed_at'] = now();
        }
        $hook->update($data);

        return $this->back('hook-'.$hook->id, 'Đã lưu hook.');
    }

    public function destroyHook(Request $request, OutfitThemeHook $hook)
    {
        $this->ownHook($request, $hook);
        $theme = $hook->outfit_theme_id;
        $hook->delete();

        return $this->back('chu-de-'.$theme, 'Đã xoá hook.');
    }

    private function themeData(Request $request, ?OutfitTheme $theme = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255',
                Rule::unique('outfit_themes', 'name')->where('user_id', $request->user()->id)->ignore($theme?->id)],
            'note' => ['nullable', 'string', 'max:5000'],
        ]);

        return ['name' => trim($data['name']), 'note' => $data['note'] ?? null];
    }

    private function hookData(Request $request): array
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:500'],
            'tags' => ['required', 'string', 'max:1000'],
        ]);
        $data = ['body' => OutfitThemeHook::normalizeBody($data['body']), 'tags' => OutfitThemeHook::normalizeTags($data['tags'])];
        if ($data['body'] === '' || $data['tags'] === '') {
            throw ValidationException::withMessages(['tags' => 'Hook cần câu hook và ít nhất 1 hashtag.']);
        }

        return $data;
    }

    private function own(Request $request, OutfitTheme $theme): void
    {
        abort_unless($theme->user_id === $request->user()->id, 404);
    }

    private function ownHook(Request $request, OutfitThemeHook $hook): void
    {
        abort_unless($hook->theme->user_id === $request->user()->id, 404);
    }

    private function back(string $anchor, string $msg)
    {
        return redirect()->to(route('themes.index').'#'.$anchor)->with('status', $msg);
    }
}
