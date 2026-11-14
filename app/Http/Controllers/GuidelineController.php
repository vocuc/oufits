<?php

namespace App\Http\Controllers;

use App\Models\Guideline;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Trang "Quy tắc": chủ shop viết quy tắc tạo outfit, ảnh bìa, đặt hook (thêm / sửa / bật tắt / xoá). */
class GuidelineController extends Controller
{
    public function index(Request $request)
    {
        $rules = $request->user()->guidelines()->orderBy('sort')->orderBy('id')->get()->groupBy('category');

        return view('guidelines.index', ['rules' => $rules, 'categories' => Guideline::CATEGORIES]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'category' => ['required', Rule::in(array_keys(Guideline::CATEGORIES))],
            'body' => ['required', 'string', 'max:5000'],
        ]);
        $sort = (int) $request->user()->guidelines()->where('category', $data['category'])->max('sort') + 1;
        $rule = $request->user()->guidelines()->create([...$data, 'body' => trim($data['body']), 'sort' => $sort]);

        return $this->back($rule, 'Đã thêm quy tắc.');
    }

    public function update(Request $request, Guideline $guideline)
    {
        $this->own($request, $guideline);
        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $guideline->update(['body' => trim($data['body']), 'is_active' => $request->boolean('is_active')]);

        return $this->back($guideline, 'Đã lưu quy tắc.');
    }

    public function destroy(Request $request, Guideline $guideline)
    {
        $this->own($request, $guideline);
        $guideline->delete();

        return redirect()->to(route('guidelines.index').'#'.$guideline->category)->with('status', 'Đã xoá quy tắc.');
    }

    private function own(Request $request, Guideline $guideline): void
    {
        abort_unless($guideline->user_id === $request->user()->id, 404);
    }

    private function back(Guideline $rule, string $msg)
    {
        return redirect()->to(route('guidelines.index').'#quy-tac-'.$rule->id)->with('status', $msg);
    }
}
