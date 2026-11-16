<?php

namespace Tests\Feature;

use App\Models\FashionModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Người mẫu: trang /models (thêm, ảnh, ghi chú, mặc định, prompt, xoá) + API /api/models (đọc, ảnh, ghi prompt). */
class FashionModelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['filesystems.cover_disk' => 'local']);
        Storage::fake('local');
    }

    public function test_page_create_photos_notes_default_and_delete(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/models')->assertOk()->assertSee('Người mẫu')->assertSee('Chưa có người mẫu nào');

        $this->post('/models', [
            'name' => 'Mẫu A', 'notes' => 'dáng mảnh, ngực to',
            'photos' => [UploadedFile::fake()->image('a.jpg', 60, 80), UploadedFile::fake()->image('b.png', 60, 80)],
        ])->assertRedirect();
        $a = FashionModel::query()->sole();
        $this->assertTrue($a->is_default);   // người mẫu đầu tiên tự thành mặc định
        $this->assertCount(2, $a->photos);
        $photo = $a->photos->first();
        Storage::disk('local')->assertExists($photo->path);
        $this->assertStringStartsWith('models/'.$user->id.'/', $photo->path);
        $this->assertContains($this->get($photo->url())->status(), [200, 302]); // disk có link tạm -> chuyển hướng
        $this->get('/models')->assertOk()->assertSee('Mẫu A')->assertSee('Mặc định')->assertSee('Chưa có prompt');

        // người mẫu thứ hai, đặt làm mặc định
        $this->post('/models', ['name' => 'Mẫu B'])->assertRedirect();
        $b = FashionModel::query()->where('name', 'Mẫu B')->sole();
        $this->assertFalse($b->is_default);
        $this->post("/models/{$b->id}/default")->assertRedirect();
        $this->assertTrue($b->fresh()->is_default);
        $this->assertFalse($a->fresh()->is_default);

        // prompt chủ shop tự sửa trên web -> hết nhãn
        $this->put("/models/{$a->id}/prompt", ['prompt' => "Người mẫu mảnh\r\nngực to"])->assertRedirect();
        $this->assertSame("Người mẫu mảnh\nngực to", $a->fresh()->prompt);
        $this->assertFalse($a->fresh()->needsPrompt());

        // xoá ảnh -> file mất + cần viết lại prompt
        $this->travel(1)->minutes();
        $this->delete("/models/{$a->id}/photos/{$photo->id}")->assertRedirect();
        Storage::disk('local')->assertMissing($photo->path);
        $this->assertCount(1, $a->fresh()->photos);
        $this->assertTrue($a->fresh()->needsPrompt());

        // xoá người mẫu mặc định -> người còn lại thành mặc định, ảnh bị xoá khỏi disk
        $left = $a->fresh()->photos->first();
        $this->delete("/models/{$b->id}")->assertRedirect('/models');
        $this->assertTrue($a->fresh()->is_default);
        $this->delete("/models/{$a->id}")->assertRedirect('/models');
        Storage::disk('local')->assertMissing($left->path);
        $this->assertSame(0, FashionModel::query()->count());
    }

    public function test_notes_change_marks_prompt_stale(): void
    {
        $user = User::factory()->create();
        $m = $user->fashionModels()->create(['name' => 'Mẫu A', 'notes' => 'mảnh', 'content_updated_at' => now()]);
        $m->setPrompt('Prompt cũ');
        $this->assertFalse($m->fresh()->needsPrompt());

        $this->travel(1)->minutes();
        $this->actingAs($user)->put("/models/{$m->id}", ['name' => 'Mẫu A', 'notes' => 'mảnh'])->assertRedirect();
        $this->assertFalse($m->fresh()->needsPrompt()); // ghi chú không đổi

        $this->put("/models/{$m->id}", ['name' => 'Mẫu A', 'notes' => 'mảnh, mông to'])->assertRedirect();
        $this->assertTrue($m->fresh()->needsPrompt());
        $this->get('/models')->assertSee('prompt cần viết lại');
    }

    public function test_api_list_photo_and_prompt(): void
    {
        $user = User::factory()->create();
        $token = $user->issueApiToken();
        $m = $user->fashionModels()->create(['name' => 'Mẫu A', 'notes' => 'ngực to mông to', 'content_updated_at' => now()]);
        $m->addPhotos([UploadedFile::fake()->image('a.jpg', 60, 80)]);
        $m->makeDefault();
        $photo = $m->photos()->first();

        $this->withToken($token)->getJson('/api/models')->assertOk()
            ->assertJsonPath('data.0.name', 'Mẫu A')->assertJsonPath('data.0.notes', 'ngực to mông to')
            ->assertJsonPath('data.0.is_default', true)->assertJsonPath('data.0.needs_prompt', true)
            ->assertJsonPath('data.0.photos.0.id', $photo->id)
            ->assertJsonPath('data.0.photos.0.url', url("/api/models/{$m->id}/photos/{$photo->id}"));
        $this->assertContains($this->withToken($token)->get("/api/models/{$m->id}/photos/{$photo->id}")->status(), [200, 302]);

        $this->withToken($token)->putJson("/api/models/{$m->id}/prompt", ['prompt' => 'Người mẫu nữ trưởng thành, dáng mảnh'])
            ->assertOk()->assertJsonPath('data.prompt', 'Người mẫu nữ trưởng thành, dáng mảnh')
            ->assertJsonPath('data.needs_prompt', false);
        $this->withToken($token)->getJson('/api/models?needs_prompt=1')->assertOk()->assertJsonCount(0, 'data');
        $this->withToken($token)->putJson("/api/models/{$m->id}/prompt", ['prompt' => ''])->assertUnprocessable();
    }

    public function test_owner_only(): void
    {
        $owner = User::factory()->create();
        $m = $owner->fashionModels()->create(['name' => 'Riêng của chủ']);
        $m->addPhotos([UploadedFile::fake()->image('a.jpg', 60, 80)]);
        $photo = $m->photos()->first();
        $other = User::factory()->create();

        $this->actingAs($other)->get('/models')->assertOk()->assertDontSee('Riêng của chủ');
        $this->get($photo->url())->assertNotFound();
        $this->put("/models/{$m->id}", ['name' => 'X'])->assertNotFound();
        $this->delete("/models/{$m->id}")->assertNotFound();
        $this->withToken($other->issueApiToken())->getJson('/api/models')->assertOk()->assertJsonCount(0, 'data');
        $this->withToken($other->issueApiToken())->get("/api/models/{$m->id}/photos/{$photo->id}")->assertNotFound();
        $this->withToken($other->issueApiToken())->putJson("/api/models/{$m->id}/prompt", ['prompt' => 'x'])->assertNotFound();
        $this->assertSame('Riêng của chủ', $m->fresh()->name);
    }
}
