<?php

namespace Tests\Feature;

use App\Models\Outfit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OutfitVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private function outfitFor(User $user, array $attrs = []): Outfit
    {
        return $user->outfits()->create(['title' => 'Len đen', 'items' => '- Áo len đen', ...$attrs]);
    }

    public function test_outfit_is_public_by_default(): void
    {
        $outfit = $this->outfitFor(User::factory()->create());

        $this->assertTrue($outfit->fresh()->is_public);
    }

    public function test_api_creates_public_by_default_and_private_on_request(): void
    {
        $token = User::factory()->create()->issueApiToken();
        $body = ['title' => 'A', 'items' => '- a', 'hashtags' => 'thudong'];

        $this->withToken($token)->postJson('/api/outfits', $body)
            ->assertCreated()->assertJsonPath('data.is_public', true);
        $this->withToken($token)->postJson('/api/outfits', [...$body, 'is_public' => false])
            ->assertCreated()->assertJsonPath('data.is_public', false);
    }

    public function test_form_can_switch_to_private(): void
    {
        $user = User::factory()->create();
        $outfit = $this->outfitFor($user);

        $this->actingAs($user)->put(route('outfits.update', $outfit), [
            'title' => 'Len đen', 'items' => '- Áo len đen', 'hashtags' => 'thudong', 'is_public' => '0',
        ])->assertRedirect();

        $this->assertFalse($outfit->fresh()->is_public);
    }

    public function test_other_user_sees_public_but_not_private_and_cannot_edit(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $public = $this->outfitFor($owner);
        $private = $this->outfitFor($owner, ['is_public' => false]);

        $this->actingAs($other)->get(route('outfits.show', $public))->assertOk()->assertDontSee('Xóa outfit này');
        $this->actingAs($other)->get(route('outfits.show', $private))->assertNotFound();
        $this->actingAs($other)->get(route('outfits.edit', $public))->assertForbidden();
        $this->actingAs($other)->delete(route('outfits.destroy', $public))->assertForbidden();
        $this->actingAs($owner)->get(route('outfits.show', $private))->assertOk();
    }
}
