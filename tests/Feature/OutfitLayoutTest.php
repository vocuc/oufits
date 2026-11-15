<?php

namespace Tests\Feature;

use App\Models\CoverTemplate;
use App\Models\OutfitLayout;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Mẫu ảnh outfit: POST/GET /api/outfit-layouts, trang /outfit-layouts (duyệt + yêu cầu), tách bạch với mẫu bìa. */
class OutfitLayoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['filesystems.cover_disk' => 'local']);
        Storage::fake('local');
    }

    public function test_api_upsert_with_prompt_and_optional_image(): void
    {
        $user = User::factory()->create();
        $token = $user->issueApiToken();
        $h = ['Accept' => 'application/json'];

        $this->withToken($token)->post('/api/outfit-layouts', [
            'code' => '02', 'name' => 'Selfie gương', 'how_to' => 'Chụp gương', 'prompt' => 'KHUNG ẢNH ... "{HOOK}"',
        ], $h)->assertCreated()->assertJsonPath('data.status', 'pending')->assertJsonPath('data.url', null)
            ->assertJsonPath('data.prompt', 'KHUNG ẢNH ... "{HOOK}"');

        $layout = OutfitLayout::query()->sole();
        $layout->update(['status' => 'approved', 'request' => 'Ánh sáng ấm hơn']);
        $this->withToken($token)->post('/api/outfit-layouts', [
            'code' => '02', 'name' => 'Selfie gương', 'image' => UploadedFile::fake()->image('a.jpg', 60, 80),
        ], $h)->assertOk()->assertJsonPath('data.status', 'approved')->assertJsonPath('data.request', 'Ánh sáng ấm hơn')
            ->assertJsonPath('data.prompt', 'KHUNG ẢNH ... "{HOOK}"');
        $layout->refresh();
        Storage::disk('local')->assertExists($layout->image_path);
        $this->assertStringStartsWith('outfit-layouts/', $layout->image_path);
        $this->assertStringContainsString('/outfit-layouts/'.$layout->id.'/image', $layout->url());

        $this->withToken($token)->getJson('/api/outfit-layouts?status=approved')->assertOk()->assertJsonCount(1, 'data');

        // câu sửa nhanh riêng của layout -> /api/compose trả về đúng câu đó; chưa có thì câu chung
        $outfit = $user->outfits()->create(['title' => 'A', 'items' => '- a']);
        $this->withToken($token)->getJson("/api/compose?ids[]={$outfit->id}")->assertOk()
            ->assertJsonPath('data.fix', \App\Support\PromptComposer::defaultFix());
        $this->withToken($token)->post('/api/outfit-layouts', ['code' => '02', 'name' => 'Selfie gương', 'fix_prompt' => 'SỬA BỐ CỤC GƯƠNG'], $h)
            ->assertOk()->assertJsonPath('data.fix_prompt', 'SỬA BỐ CỤC GƯƠNG');
        $this->withToken($token)->getJson("/api/compose?ids[]={$outfit->id}")->assertOk()
            ->assertJsonPath('data.fix', 'SỬA BỐ CỤC GƯƠNG');
        // tách bạch với mẫu bìa
        $this->assertSame(0, CoverTemplate::query()->count());
        $this->withToken($token)->getJson('/api/cover-templates')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_page_review_and_owner_only(): void
    {
        $user = User::factory()->create();
        $layout = $user->outfitLayouts()->create(['code' => '01', 'name' => 'Lookbook 2 cột', 'how_to' => 'Người mẫu trái']);
        $cover = $user->coverTemplates()->create(['code' => '01', 'name' => 'Dải tối mờ']);

        $this->actingAs($user)->get('/outfit-layouts')->assertOk()
            ->assertSee('Mẫu ảnh outfit')->assertSee('01 · Lookbook 2 cột')->assertSee('Chưa có ảnh mẫu')
            ->assertSee('Xem / sửa prompt layout')->assertSee('Lưu prompt')->assertDontSee('Dải tối mờ');
        $this->actingAs($user)->get('/cover-templates')->assertOk()->assertSee('Dải tối mờ')->assertDontSee('Lookbook 2 cột')
            ->assertDontSee('Xem / sửa prompt layout');

        $this->actingAs($user)->put("/outfit-layouts/{$layout->id}", ['request' => 'Nền sáng hơn', 'status' => 'approved'])
            ->assertRedirect();
        $layout->refresh();
        $this->assertSame(['approved', 'Nền sáng hơn'], [$layout->status, $layout->request]);
        $this->assertSame('pending', $cover->fresh()->status);

        // id của mẫu bìa không dùng được ở route layout (bảng khác)
        $this->actingAs($user)->put('/outfit-layouts/'.($layout->id + $cover->id + 99), ['request' => 'x'])->assertNotFound();
        $other = User::factory()->create();
        $this->actingAs($other)->put("/outfit-layouts/{$layout->id}", ['request' => 'hack'])->assertNotFound();
        $this->actingAs($other)->get("/outfit-layouts/{$layout->id}/image")->assertNotFound();
    }

    public function test_edit_prompt_keeps_status_owner_only(): void
    {
        $user = User::factory()->create();
        $layout = $user->outfitLayouts()->create(['code' => '08', 'name' => 'Sửa prompt', 'prompt' => 'cũ', 'status' => 'approved']);

        $this->actingAs($user)->put("/outfit-layouts/{$layout->id}/prompt", ['prompt' => "Dáng mới\r\nđứng nghiêng"])
            ->assertRedirect();
        $layout->refresh();
        $this->assertSame(["Dáng mới\nđứng nghiêng", 'approved'], [$layout->prompt, $layout->status]);
        $this->actingAs($user)->get('/outfit-layouts')->assertDontSee('Câu sửa nhanh');

        $this->actingAs($user)->put("/outfit-layouts/{$layout->id}/prompt", ['prompt' => ''])->assertSessionHasErrors('prompt');
        $this->actingAs(User::factory()->create())->put("/outfit-layouts/{$layout->id}/prompt", ['prompt' => 'hack'])->assertNotFound();
        $this->assertSame("Dáng mới\nđứng nghiêng", $layout->fresh()->prompt);
    }

    public function test_delete_layout_owner_only(): void
    {
        $user = User::factory()->create();
        $layout = $user->outfitLayouts()->create(['code' => '07', 'name' => 'Thử xoá']);
        $cover = $user->coverTemplates()->create(['code' => '07', 'name' => 'Bìa giữ lại']);
        $layout->replaceImage(UploadedFile::fake()->image('mau.jpg', 600, 800));
        $path = $layout->fresh()->image_path;

        $this->actingAs($user)->get('/outfit-layouts')->assertSee('Xoá hẳn mẫu này');
        $this->actingAs($user)->get('/cover-templates')->assertSee('Xoá hẳn mẫu này');

        $other = User::factory()->create();
        $this->actingAs($other)->delete("/outfit-layouts/{$layout->id}")->assertNotFound();
        $this->assertNotNull($layout->fresh());

        $this->actingAs($user)->delete("/outfit-layouts/{$layout->id}")->assertRedirect('/outfit-layouts');
        $this->assertNull($layout->fresh());
        Storage::disk('local')->assertMissing($path);
        $this->assertNotNull($cover->fresh());

        // Mẫu ảnh bìa cũng xoá được, chỉ chủ của mẫu
        $this->actingAs($other)->delete("/cover-templates/{$cover->id}")->assertNotFound();
        $this->actingAs($user)->delete("/cover-templates/{$cover->id}")->assertRedirect('/cover-templates');
        $this->assertNull($cover->fresh());
    }
}
