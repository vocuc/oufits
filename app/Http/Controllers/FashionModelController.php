<?php

namespace App\Http\Controllers;

use App\Models\FashionModel;
use Illuminate\Http\Request;

/**
 * Trang "Người mẫu": chủ shop thêm người mẫu (tên, ảnh, ghi chú), đặt mặc định, xem / sửa prompt mô tả người mẫu.
 * Claude đọc ảnh + ghi chú qua API rồi viết prompt; ghi chú / ảnh đổi sau đó -> nhãn "cần viết lại prompt".
 */
class FashionModelController extends Controller
{
    private const PHOTO_RULES = ['image', 'mimes:jpg,jpeg,png,webp', 'max:20480'];

    public function index(Request $request)
    {
        $models = $request->user()->fashionModels()->with('photos')
            ->orderByDesc('is_default')->orderBy('name')->orderBy('id')->get();

        return view('models.index', ['models' => $models]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:10000'],
            'photos' => ['nullable', 'array', 'max:20'],
            'photos.*' => self::PHOTO_RULES,
        ]);
        $user = $request->user();
        $model = $user->fashionModels()->create([
            'name' => trim($data['name']),
            'notes' => $this->clean($data['notes'] ?? null),
            'content_updated_at' => now(),
        ]);
        $model->addPhotos($request->file('photos', []));
        if (! $user->fashionModels()->where('is_default', true)->exists()) {
            $model->makeDefault();
        }

        return $this->back($model, "Đã thêm người mẫu {$model->name}.");
    }

    public function update(Request $request, string $model)
    {
        $m = $this->find($request, $model);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:10000'],
        ]);
        $notes = $this->clean($data['notes'] ?? null);
        $changed = $notes !== $m->notes;
        $m->fill(['name' => trim($data['name']), 'notes' => $notes]);
        if ($changed) {
            $m->content_updated_at = now();
        }
        $m->save();

        return $this->back($m, $changed ? 'Đã lưu ghi chú — prompt người mẫu cần viết lại.' : 'Đã lưu.');
    }

    public function addPhotos(Request $request, string $model)
    {
        $m = $this->find($request, $model);
        $request->validate(['photos' => ['required', 'array', 'min:1', 'max:20'], 'photos.*' => self::PHOTO_RULES]);
        $n = $m->addPhotos($request->file('photos'));

        return $this->back($m, "Đã thêm {$n} ảnh.");
    }

    public function destroyPhoto(Request $request, string $model, string $photo)
    {
        $m = $this->find($request, $model);
        $p = $m->photos()->whereKey((int) $photo)->firstOrFail();
        $p->deleteFiles();
        $p->delete();
        $m->touchContent();

        return $this->back($m, 'Đã xoá ảnh.');
    }

    public function photo(Request $request, string $model, string $photo)
    {
        $m = $this->find($request, $model);

        return $m->photos()->whereKey((int) $photo)->firstOrFail()->response($request->boolean('thumb'));
    }

    public function updatePrompt(Request $request, string $model)
    {
        $m = $this->find($request, $model);
        $data = $request->validate(['prompt' => ['nullable', 'string', 'max:20000']]);
        $m->setPrompt($data['prompt'] ?? null);

        return $this->back($m, 'Đã lưu prompt người mẫu.');
    }

    public function makeDefault(Request $request, string $model)
    {
        $m = $this->find($request, $model);
        $m->makeDefault();

        return $this->back($m, "{$m->name} là người mẫu mặc định.");
    }

    public function destroy(Request $request, string $model)
    {
        $m = $this->find($request, $model);
        $name = $m->name;
        $wasDefault = $m->is_default;
        $m->delete();
        if ($wasDefault) {
            $request->user()->fashionModels()->orderBy('id')->first()?->makeDefault();
        }

        return redirect()->route('models.index')->with('status', "Đã xoá người mẫu {$name}.");
    }

    private function find(Request $request, string $id): FashionModel
    {
        return $request->user()->fashionModels()->whereKey((int) $id)->firstOrFail();
    }

    private function clean(?string $text): ?string
    {
        $text = trim(str_replace("\r\n", "\n", (string) $text));

        return $text === '' ? null : $text;
    }

    private function back(FashionModel $m, string $msg)
    {
        return redirect()->to(route('models.index').'#nguoi-mau-'.$m->id)->with('status', $msg);
    }
}
