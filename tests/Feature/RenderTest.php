<?php

namespace Tests\Feature;

use App\Models\OutfitRender;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Ảnh gốc lô phối đồ: POST /api/renders, GET /api/renders/used-outfits, trang /renders. */
class RenderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['filesystems.cover_disk' => 'local']);
        Storage::fake('local');
    }

    public function test_store_render_and_list_used_outfits(): void
    {
        $user = User::factory()->create();
        $token = $user->issueApiToken();
        $a = $user->outfits()->create(['title' => 'A', 'items' => '- a']);
        $b = $user->outfits()->create(['title' => 'B', 'items' => '- b']);

        foreach ([[$a, 7], [$b, 7], [$a, 8]] as [$outfit, $product]) {
            $this->withToken($token)->post('/api/renders', [
                'image' => UploadedFile::fake()->image('r.jpg', 60, 80),
                'outfit_id' => $outfit->id, 'a51_product_id' => $product, 'a51_user_id' => 3,
                'a51_product_name' => 'Giày', 'a51_batch_id' => 1, 'a51_output_id' => 2,
            ], ['Accept' => 'application/json'])->assertCreated()->assertJsonPath('data.outfit_id', $outfit->id);
        }
        $this->assertSame(3, OutfitRender::query()->count());

        $this->withToken($token)->getJson('/api/renders/used-outfits?a51_product_id=7')
            ->assertOk()->assertJsonPath('data', [$a->id, $b->id]);
        $this->withToken($token)->getJson('/api/renders/used-outfits?a51_product_id=8&a51_user_id=99')
            ->assertOk()->assertJsonPath('data', []);

        $foreign = User::factory()->create()->outfits()->create(['title' => 'X', 'items' => '- x']);
        $this->withToken($token)->post('/api/renders', [
            'image' => UploadedFile::fake()->image('r.jpg'), 'outfit_id' => $foreign->id,
        ], ['Accept' => 'application/json'])->assertUnprocessable();
    }

    public function test_library_page_filters_by_outfit_and_hides_other_users(): void
    {
        $user = User::factory()->create();
        $token = $user->issueApiToken();
        $a = $user->outfits()->create(['title' => 'Outfit Alpha', 'items' => '- a']);
        $b = $user->outfits()->create(['title' => 'Outfit Beta', 'items' => '- b']);
        foreach ([$a, $b] as $outfit) {
            $this->withToken($token)->post('/api/renders', ['image' => UploadedFile::fake()->image('r.jpg'), 'outfit_id' => $outfit->id],
                ['Accept' => 'application/json'])->assertCreated();
        }

        $this->actingAs($user)->get('/renders')->assertOk()->assertSee('Outfit Alpha')->assertSee('Outfit Beta');
        $this->actingAs($user)->get('/renders?outfit='.$a->id)->assertOk()->assertSee('Outfit Alpha')->assertDontSee('Outfit Beta');
        $this->actingAs($user)->get(route('outfits.show', $a))->assertOk()->assertSee('Ảnh đã tạo (1)');

        // Trang danh sách outfit: ảnh bìa = ảnh tạo gần nhất của outfit
        $this->withToken($token)->post('/api/renders', ['image' => UploadedFile::fake()->image('r2.jpg'), 'outfit_id' => $a->id],
            ['Accept' => 'application/json'])->assertCreated();
        $latest = OutfitRender::query()->where('outfit_id', $a->id)->latest('id')->firstOrFail();
        $this->actingAs($user)->get('/')->assertOk()->assertSee($latest->url(true), false)
            ->assertDontSee(OutfitRender::query()->where('outfit_id', $a->id)->oldest('id')->firstOrFail()->url(true), false);

        $render = OutfitRender::query()->firstOrFail();
        $this->assertContains($this->actingAs($user)->get(route('renders.image', $render))->status(), [200, 302]);
        $this->actingAs(User::factory()->create())->get(route('renders.image', $render))->assertNotFound();

        // Nút xoá trên thẻ outfit: xoá outfit + ảnh Thư viện ảnh của nó, quay lại đúng bộ lọc đang xem
        $this->actingAs($user)->get('/')->assertSee('Xóa outfit này');
        $paths = OutfitRender::query()->where('outfit_id', $b->id)->pluck('image_path');
        $this->actingAs($user)->from('/?tags=abc')->delete(route('outfits.destroy', $b))->assertRedirect('/?tags=abc');
        $this->assertNull($b->fresh());
        $this->assertSame(0, OutfitRender::query()->where('outfit_id', $b->id)->count());
        foreach ($paths as $path) {
            Storage::disk('local')->assertMissing($path);
        }
        $this->actingAs($user)->from(route('outfits.show', $a))->delete(route('outfits.destroy', $a))->assertRedirect(route('outfits.index'));
    }
}
