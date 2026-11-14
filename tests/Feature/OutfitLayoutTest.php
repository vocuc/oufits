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
            ->assertSee('Xem prompt layout')->assertDontSee('Dải tối mờ');
        $this->actingAs($user)->get('/cover-templates')->assertOk()->assertSee('Dải tối mờ')->assertDontSee('Lookbook 2 cột')
            ->assertDontSee('Xem prompt layout');

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
}
