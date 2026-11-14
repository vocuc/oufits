<?php

namespace App\Http\Controllers;

use App\Models\CoverTemplate;
use App\Models\Outfit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Trang "Mẫu ảnh bìa": các mẫu ảnh bìa lô phối đồ. Chủ shop duyệt / bỏ từng mẫu và ghi yêu cầu;
 * worker chỉ làm bìa theo mẫu đã duyệt (GET /api/cover-templates?status=approved).
 * OutfitLayoutController ("Mẫu ảnh outfit") kế thừa, chỉ đổi model + chữ trên trang.
 */
class CoverTemplateController extends Controller
{
    /** @var class-string<CoverTemplate> */
    protected string $model = CoverTemplate::class;

    protected function page(): array
    {
        return [
            'title' => 'Mẫu ảnh bìa',
            'intro' => 'Các cách làm ảnh bìa cho lô phối đồ A51. Worker phối đồ chỉ làm bìa theo mẫu đã duyệt và đọc cột "Yêu cầu" của mẫu đó trước khi làm.',
            'show_prompt' => false,
        ];
    }

    public function index(Request $request)
    {
        $status = $request->query('status');
        $status = array_key_exists((string) $status, CoverTemplate::STATUSES) ? $status : null;
        $all = $this->query($request);
        $counts = (clone $all)->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');
        $templates = $all->when($status, fn ($q) => $q->where('status', $status))
            ->orderBy('sort')->orderBy('code')->get();

        return view('cover-templates.index', [
            'templates' => $templates, 'status' => $status, 'counts' => $counts,
            'route' => ($this->model)::ROUTE, 'page' => $this->page(),
        ]);
    }

    /** Lưu yêu cầu và / hoặc trạng thái duyệt của một mẫu (cùng một form). */
    public function update(Request $request, string $template)
    {
        $t = $this->find($request, $template);
        $data = $request->validate([
            'request' => ['nullable', 'string', 'max:5000'],
            'status' => ['nullable', Rule::in(array_keys(CoverTemplate::STATUSES))],
        ]);
        $text = trim((string) ($data['request'] ?? '')) ?: null;
        if ($text !== $t->request) {
            $t->fill(['request' => $text, 'request_updated_at' => now()]);
        }
        if (! empty($data['status']) && $data['status'] !== $t->status) {
            $t->fill(['status' => $data['status'], 'reviewed_at' => now()]);
        }
        $t->save();

        return redirect()->to(url()->previous(route(($this->model)::ROUTE.'.index')).'#mau-'.$t->id)
            ->with('status', "Đã lưu mẫu {$t->code} ({$t->statusLabel()}).");
    }

    public function image(Request $request, string $template)
    {
        $t = $this->find($request, $template);
        abort_unless($t->image_path, 404);
        $path = $request->boolean('thumb') && $t->thumb_path ? $t->thumb_path : $t->image_path;
        $disk = Outfit::coverDisk();
        if ($disk->providesTemporaryUrls()) {
            return redirect()->away($disk->temporaryUrl($path, now()->addHour()))->header('Cache-Control', 'private, max-age=3000');
        }
        abort_unless($disk->exists($path), 404);

        return $disk->response($path, null, ['Cache-Control' => 'private, max-age=31536000, immutable']);
    }

    protected function query(Request $request)
    {
        return ($this->model)::query()->where('user_id', $request->user()->id);
    }

    protected function find(Request $request, string $id): CoverTemplate
    {
        return $this->query($request)->whereKey((int) $id)->firstOrFail();
    }
}
