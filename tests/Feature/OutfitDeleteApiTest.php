<?php

namespace Tests\Feature;

use App\Models\Hashtag;
use App\Models\ItemPair;
use App\Models\Outfit;
use App\Models\OutfitRating;
use App\Models\OutfitRender;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** DELETE /api/outfits/{id} và DELETE /api/outfits?confirm=xoa-het-outfit. */
class OutfitDeleteApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['filesystems.cover_disk' => 'local']);
        Storage::fake('local');
    }

    private function outfitWithRender(User $user, string $title): Outfit
    {
        $outfit = $user->outfits()->create(['title' => $title, 'items' => '- a']);
        $outfit->forceFill(['cover_path' => "ootd/{$title}.webp"])->save();
        $outfit->syncHashtags("tag{$title}");
        Storage::disk('local')->put("ootd/{$title}.webp", 'x');
        Storage::disk('local')->put("renders/{$title}.jpg", 'x');
        $render = OutfitRender::create([
            'user_id' => $user->id, 'outfit_id' => $outfit->id,
            'image_path' => "renders/{$title}.jpg", 'a51_product_id' => 1,
        ]);
        OutfitRating::create(['user_id' => $user->id, 'outfit_id' => $outfit->id, 'render_id' => $render->id, 'rating' => 8]);

        return $outfit;
    }

    public function test_delete_all_requires_confirm(): void
    {
        $user = User::factory()->create();
        $this->outfitWithRender($user, 'a');

        $this->withToken($user->issueApiToken())->deleteJson('/api/outfits')->assertUnprocessable();
        $this->assertSame(1, Outfit::count());
    }

    public function test_delete_all_wipes_only_own_outfits_and_files(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->outfitWithRender($user, 'a');
        $this->outfitWithRender($user, 'b');
        $this->outfitWithRender($other, 'c');
        ItemPair::create(['user_id' => $user->id, 'level' => 'cat', 'a' => 'x', 'b' => 'y', 'score' => 1, 'count' => 1, 'expected' => 1, 'lift' => 1]);

        $this->withToken($user->issueApiToken())->deleteJson('/api/outfits?confirm=xoa-het-outfit')
            ->assertOk()
            ->assertJsonPath('data.outfits', 2)
            ->assertJsonPath('data.renders', 2)
            ->assertJsonPath('data.ratings', 2)
            ->assertJsonPath('data.item_pairs', 1);

        $this->assertSame(['c'], Outfit::pluck('title')->all());
        $this->assertSame(1, OutfitRender::count());
        $this->assertSame(1, OutfitRating::count());
        $this->assertSame(['tagc'], Hashtag::pluck('name')->all());
        Storage::disk('local')->assertMissing(['ootd/a.webp', 'renders/a.jpg', 'ootd/b.webp', 'renders/b.jpg']);
        Storage::disk('local')->assertExists(['ootd/c.webp', 'renders/c.jpg']);
    }

    public function test_delete_one_outfit(): void
    {
        $user = User::factory()->create();
        $a = $this->outfitWithRender($user, 'a');
        $this->outfitWithRender($user, 'b');
        $token = $user->issueApiToken();

        $this->withToken($token)->deleteJson("/api/outfits/{$a->id}")->assertOk()->assertJsonPath('data.outfits', 1);
        $this->assertSame(['b'], Outfit::pluck('title')->all());

        $c = $this->outfitWithRender(User::factory()->create(), 'c');
        $this->withToken($token)->deleteJson("/api/outfits/{$c->id}")->assertNotFound();
    }
}
