<?php

namespace App\Http\Controllers;

use App\Models\CoverTemplate;
use App\Models\Outfit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Trang "Thư viện ảnh bìa": các mẫu ảnh bìa lô phối đồ. Chủ shop duyệt / bỏ từng mẫu và ghi yêu cầu;
 * worker chỉ làm bìa theo mẫu đã duyệt (GET /api/cover-templates?status=approved).
 */
class CoverTemplateController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status');
        $status = array_key_exists((string) $status, CoverTemplate::STATUSES) ? $status : null;
        $all = $request->user()->coverTemplates();
        $counts = (clone $all)->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');
        $templates = $all->when($status, fn ($q) => $q->where('status', $status))
            ->orderBy('sort')->orderBy('code')->get();

        return view('cover-templates.index', compact('templates', 'status', 'counts'));
    }

    /** Lưu yêu cầu và / hoặc trạng thái duyệt của một mẫu (cùng một form). */
    public function update(Request $request, CoverTemplate $coverTemplate)
    {
        abort_unless($coverTemplate->user_id === $request->user()->id, 404);
        $data = $request->validate([
            'request' => ['nullable', 'string', 'max:5000'],
            'status' => ['nullable', Rule::in(array_keys(CoverTemplate::STATUSES))],
        ]);
        $text = trim((string) ($data['request'] ?? '')) ?: null;
        if ($text !== $coverTemplate->request) {
            $coverTemplate->fill(['request' => $text, 'request_updated_at' => now()]);
        }
        if (! empty($data['status']) && $data['status'] !== $coverTemplate->status) {
            $coverTemplate->fill(['status' => $data['status'], 'reviewed_at' => now()]);
        }
        $coverTemplate->save();

        return redirect()->to(url()->previous(route('cover-templates.index')).'#mau-'.$coverTemplate->id)
            ->with('status', "Đã lưu mẫu {$coverTemplate->code} ({$coverTemplate->statusLabel()}).");
    }

    public function image(Request $request, CoverTemplate $coverTemplate)
    {
        abort_unless($coverTemplate->user_id === $request->user()->id && $coverTemplate->image_path, 404);
        $path = $request->boolean('thumb') && $coverTemplate->thumb_path ? $coverTemplate->thumb_path : $coverTemplate->image_path;
        $disk = Outfit::coverDisk();
        if ($disk->providesTemporaryUrls()) {
            return redirect()->away($disk->temporaryUrl($path, now()->addHour()))->header('Cache-Control', 'private, max-age=3000');
        }
        abort_unless($disk->exists($path), 404);

        return $disk->response($path, null, ['Cache-Control' => 'private, max-age=31536000, immutable']);
    }
}
