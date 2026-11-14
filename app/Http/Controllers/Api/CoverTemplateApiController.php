<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CoverTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Mẫu ảnh bìa lô phối đồ (worker / Claude). OutfitLayoutApiController (Mẫu ảnh outfit) kế thừa.
 *   GET  /api/cover-templates?status=approved -> các mẫu (kèm status, request = yêu cầu chủ shop ghi trên web)
 *   POST /api/cover-templates (multipart) code, name, hook_type?, how_to?, sort?, image? -> tạo / cập nhật theo code.
 *        Mẫu mới luôn ở "Chờ duyệt". API không bao giờ đổi status hay request (chỉ chủ shop sửa trên web).
 */
class CoverTemplateApiController extends Controller
{
    /** @var class-string<CoverTemplate> */
    protected string $model = CoverTemplate::class;

    /** Cột mô tả API được ghi (ngoài code / image). */
    protected array $fields = ['name', 'hook_type', 'how_to', 'sort'];

    protected function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:40'],
            'name' => ['required', 'string', 'max:255'],
            'hook_type' => ['nullable', 'string', 'max:100'],
            'how_to' => ['nullable', 'string', 'max:10000'],
            'sort' => ['nullable', 'integer', 'min:0'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:20480'],
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate(['status' => ['nullable', Rule::in(array_keys(CoverTemplate::STATUSES))]]);
        $rows = ($this->model)::query()->where('user_id', $request->user()->id)
            ->when($data['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->orderBy('sort')->orderBy('code')->get();

        return response()->json(['data' => $rows->map->toApi()->values()]);
    }

    public function upsert(Request $request): JsonResponse
    {
        $data = $request->validate($this->rules());
        $template = ($this->model)::query()->firstOrNew(['user_id' => $request->user()->id, 'code' => $data['code']]);
        $template->fill(collect($data)->only($this->fields)->filter(fn ($v) => $v !== null)->all());
        $template->sort ??= 0;
        $template->status ??= CoverTemplate::PENDING;
        $created = ! $template->exists;
        $template->save();
        if ($request->hasFile('image')) {
            $template->replaceImage($request->file('image'));
        }

        return response()->json(['data' => $template->fresh()->toApi()], $created ? 201 : 200);
    }
}
