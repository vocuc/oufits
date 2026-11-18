<?php

namespace Tests\Feature;

use App\Models\CoverTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Mẫu ảnh bìa: POST/GET /api/cover-templates, trang /cover-templates (duyệt + yêu cầu). */
class CoverTemplateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['filesystems.cover_disk' => 'local']);
        Storage::fake('local');
    }

    private function upsert(string $token, array $data)
    {
        return $this->withToken($token)->post('/api/cover-templates', $data, ['Accept' => 'application/json']);
    }

    public function test_api_upsert_by_code_keeps_owner_status_and_request(): void
    {
        $user = User::factory()->create();
        $token = $user->issueApiToken();

        $this->upsert($token, [
            'code' => '01', 'name' => 'Dải tối mờ', 'hook_type' => 'Tò mò', 'how_to' => 'Dải đen mờ phía trên',
            'image' => UploadedFile::fake()->image('a.jpg', 60, 80),
        ])->assertCreated()->assertJsonPath('data.status', 'pending')->assertJsonPath('data.code', '01');

        $t = CoverTemplate::query()->sole();
        $first = $t->image_path;
        $firstUrl = $t->url();
        Storage::disk('local')->assertExists($first);
        $t->update(['status' => 'approved', 'request' => 'Chữ to hơn']);

        $this->upsert($token, ['code' => '01', 'name' => 'Dải tối mờ v2', 'image' => UploadedFile::fake()->image('b.jpg')])
            ->assertOk()->assertJsonPath('data.status', 'approved')->assertJsonPath('data.request', 'Chữ to hơn')
            ->assertJsonPath('data.how_to', 'Dải đen mờ phía trên');
        Storage::disk('local')->assertMissing($first);
        $this->assertNotSame($firstUrl, $t->fresh()->url(), 'Thay ảnh thì link ảnh phải đổi (tránh cache ảnh cũ)');
        $user->refresh();
        $this->assertContains($this->actingAs($user)->get($t->fresh()->url())->status(), [200, 302]);
        $this->assertSame(1, CoverTemplate::query()->count());

        $this->upsert($token, ['code' => '02', 'name' => 'TikTok'])->assertCreated();
        $this->withToken($token)->getJson('/api/cover-templates?status=approved')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.code', '01');
        $this->withToken($token)->getJson('/api/cover-templates')->assertOk()->assertJsonCount(2, 'data');
        $this->withToken(User::factory()->create()->issueApiToken())->getJson('/api/cover-templates')->assertJsonCount(0, 'data');
    }

    public function test_page_saves_request_and_review_status_for_owner_only(): void
    {
        $user = User::factory()->create();
        $t = $user->coverTemplates()->create(['code' => '05', 'name' => 'Tạp chí', 'how_to' => 'Chữ có chân']);

        $this->actingAs($user)->get('/cover-templates')->assertOk()
            ->assertSee('Mẫu ảnh bìa')->assertSee('05 · Tạp chí')->assertSee('Chờ duyệt');

        $this->actingAs($user)->put("/cover-templates/{$t->id}", ['request' => '  Đổi màu hồng đậm  ', 'status' => ''])
            ->assertRedirect();
        $t->refresh();
        $this->assertSame('Đổi màu hồng đậm', $t->request);
        $this->assertSame('pending', $t->status);
        $this->assertNotNull($t->request_updated_at);

        $this->actingAs($user)->put("/cover-templates/{$t->id}", ['request' => 'Đổi màu hồng đậm', 'status' => 'approved']);
        $t->refresh();
        $this->assertSame('approved', $t->status);
        $this->assertNotNull($t->reviewed_at);
        $this->actingAs($user)->get('/cover-templates?status=approved')->assertSee('05 · Tạp chí');
        $this->actingAs($user)->get('/cover-templates?status=rejected')->assertDontSee('05 · Tạp chí');

        $this->actingAs($user)->put("/cover-templates/{$t->id}", ['status' => 'xyz'])->assertSessionHasErrors('status');

        $other = User::factory()->create();
        $this->actingAs($other)->put("/cover-templates/{$t->id}", ['request' => 'hack'])->assertNotFound();
        $this->actingAs($other)->get('/cover-templates')->assertDontSee('Tạp chí');
        $this->assertSame('Đổi màu hồng đậm', $t->fresh()->request);
    }

    public function test_owner_picks_lot_type_on_page_and_api_cannot_change_it(): void
    {
        $user = User::factory()->create();
        $token = $user->issueApiToken();
        $this->upsert($token, ['code' => '17', 'name' => 'TikTok'])->assertCreated()->assertJsonPath('data.lot_type', 'all');
        $t = CoverTemplate::query()->sole();

        $this->actingAs($user)->get('/cover-templates')->assertOk()->assertSee('Dùng cho: Cả hai loại lô')
            ->assertSee('Lô không sản phẩm');
        $this->actingAs($user)->put("/cover-templates/{$t->id}", ['request' => '', 'status' => '', 'lot_type' => 'no_product'])
            ->assertRedirect();
        $this->assertSame('no_product', $t->fresh()->lot_type);
        $this->assertSame('pending', $t->fresh()->status);
        $this->actingAs($user)->get('/cover-templates')->assertSee('Dùng cho: Lô không sản phẩm');

        // API upsert theo code không đổi lot_type (chỉ chủ shop chọn trên web); GET trả lot_type cho worker
        $this->upsert($token, ['code' => '17', 'name' => 'TikTok v2', 'lot_type' => 'product'])->assertOk()
            ->assertJsonPath('data.lot_type', 'no_product');
        $this->withToken($token)->getJson('/api/cover-templates')->assertJsonPath('data.0.lot_type', 'no_product');

        $this->actingAs($user)->put("/cover-templates/{$t->id}", ['lot_type' => 'xyz'])->assertSessionHasErrors('lot_type');
        // Trang Mẫu ảnh outfit không có lựa chọn này
        $this->actingAs($user)->get('/outfit-layouts')->assertOk()->assertDontSee('Dùng cho:');
    }
}
