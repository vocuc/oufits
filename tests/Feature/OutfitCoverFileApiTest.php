<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** GET /api/outfits/{id}/cover: worker phối đồ tải ảnh gốc outfit (chỉ outfit của mình, có ảnh bìa). */
class OutfitCoverFileApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['filesystems.cover_disk' => 'local']);
        Storage::fake('local');
    }

    public function test_owner_downloads_cover_others_get_404(): void
    {
        $user = User::factory()->create();
        $token = $user->issueApiToken();
        $outfit = $user->outfits()->create(['title' => 'A', 'items' => '- a']);
        $none = $user->outfits()->create(['title' => 'B', 'items' => '- b']);
        $outfit->forceFill(['cover_path' => 'ootd/a.webp'])->save();
        Storage::disk('local')->put('ootd/a.webp', 'anh');

        $res = $this->withToken($token)->get("/api/outfits/{$outfit->id}/cover");
        $this->assertContains($res->status(), [200, 302]); // disk có link tạm -> chuyển hướng
        $this->withToken($token)->get("/api/outfits/{$none->id}/cover")->assertNotFound();

        $other = User::factory()->create()->issueApiToken();
        $this->withToken($other)->get("/api/outfits/{$outfit->id}/cover")->assertNotFound();
        $this->withoutToken()->getJson("/api/outfits/{$outfit->id}/cover")->assertUnauthorized();
    }
}
