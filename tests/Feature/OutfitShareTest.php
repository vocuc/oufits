<?php

namespace Tests\Feature;

use App\Models\Outfit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OutfitShareTest extends TestCase
{
    use RefreshDatabase;

    private function outfitFor(User $user, array $attrs = []): Outfit
    {
        return $user->outfits()->create(['title' => 'Len đen', 'items' => '- Áo len đen', ...$attrs]);
    }

    public function test_owner_adds_and_removes_share_by_email(): void
    {
        $me = User::factory()->create();
        $friend = User::factory()->create(['email' => 'ban@example.com']);

        $this->actingAs($me)->post('/settings/shares', ['email' => 'BAN@example.com'])->assertSessionHasNoErrors();
        $this->assertTrue($friend->canViewOutfitsOf($me->id));
        $this->assertFalse($me->canViewOutfitsOf($friend->id));

        $this->actingAs($me)->post('/settings/shares', ['email' => 'khongco@example.com'])->assertSessionHasErrors('email');
        $this->actingAs($me)->post('/settings/shares', ['email' => $me->email])->assertSessionHasErrors('email');

        $this->actingAs($me)->get('/settings')->assertOk()->assertSee('ban@example.com');

        $this->actingAs($me)->delete("/settings/shares/{$friend->id}")->assertRedirect();
        $this->assertFalse($friend->fresh()->canViewOutfitsOf($me->id));
    }

    public function test_shared_viewer_sees_all_outfits_on_web_but_cannot_edit(): void
    {
        $me = User::factory()->create(['name' => 'Chủ shop']);
        $friend = User::factory()->create();
        $stranger = User::factory()->create();
        $private = $this->outfitFor($me, ['title' => 'Outfit riêng tư', 'is_public' => false]);
        $me->sharedWith()->attach($friend->id);

        $this->actingAs($friend)->get('/')->assertOk()->assertSee('Chủ shop');
        $this->actingAs($friend)->get("/?owner={$me->id}")->assertOk()
            ->assertSee('Outfit riêng tư')->assertDontSee('Xóa outfit này');
        $this->actingAs($friend)->get("/outfits/{$private->id}")->assertOk();
        $this->actingAs($friend)->get("/outfits/{$private->id}/edit")->assertForbidden();
        $this->actingAs($friend)->delete("/outfits/{$private->id}")->assertForbidden();
        $this->actingAs($friend)->getJson("/?owner={$me->id}&offset=0")->assertOk()->assertJsonPath('next_offset', null);

        $this->actingAs($stranger)->get("/?owner={$me->id}")->assertNotFound();
        $this->actingAs($stranger)->get("/outfits/{$private->id}")->assertNotFound();
    }

    public function test_api_lists_outfits_of_owner_who_shared(): void
    {
        $me = User::factory()->create();
        $friend = User::factory()->create(['name' => 'Bạn']);
        $token = $friend->issueApiToken();
        $this->outfitFor($me, ['title' => 'Riêng tư của tôi', 'is_public' => false]);
        $this->outfitFor($me, ['title' => 'Công khai của tôi']);
        $this->outfitFor($friend, ['title' => 'Của bạn']);

        // Chưa chia sẻ -> 404
        $this->withToken($token)->getJson("/api/outfits?owner={$me->id}")->assertNotFound();

        $me->sharedWith()->attach($friend->id);

        $titles = collect($this->withToken($token)->getJson("/api/outfits?owner={$me->id}")->assertOk()->json('data'))
            ->pluck('title')->sort()->values()->all();
        $this->assertSame(['Công khai của tôi', 'Riêng tư của tôi'], $titles);

        // Mặc định (all) gồm cả outfit riêng tư được chia sẻ
        $this->withToken($token)->getJson('/api/outfits')->assertOk()->assertJsonCount(3, 'data');

        $this->withToken($token)->getJson('/api/outfit-shares')->assertOk()
            ->assertJsonPath('data.shared_owners.0.id', $me->id)
            ->assertJsonCount(0, 'data.shared_with');
    }
}
