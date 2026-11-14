<?php

namespace Tests\Feature;

use App\Models\Hashtag;
use App\Models\Outfit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OutfitHashtagApiTest extends TestCase
{
    use RefreshDatabase;

    private function outfit(User $user, array $tags): Outfit
    {
        $outfit = $user->outfits()->create(['title' => 'Outfit', 'items' => '- a']);
        $outfit->syncHashtags($tags);

        return $outfit;
    }

    public function test_adds_and_removes_hashtags_keeping_others(): void
    {
        $user = User::factory()->create();
        $outfit = $this->outfit($user, ['thudong', 'vay', 'giayboot']);

        $this->withToken($user->issueApiToken())
            ->putJson("/api/outfits/{$outfit->id}/hashtags", ['add' => ['Hẹn hò', '#dichoi'], 'remove' => 'vay'])
            ->assertOk()
            ->assertJsonPath('data.hashtags', ['dichoi', 'giayboot', 'henho', 'thudong']);
    }

    public function test_remove_wins_and_unused_hashtags_are_pruned(): void
    {
        $user = User::factory()->create();
        $outfit = $this->outfit($user, ['vay']);

        $this->withToken($user->issueApiToken())
            ->putJson("/api/outfits/{$outfit->id}/hashtags", ['add' => ['henho', 'vay'], 'remove' => ['vay']])
            ->assertOk()
            ->assertJsonPath('data.hashtags', ['henho']);

        $this->assertFalse(Hashtag::where('name', 'vay')->exists());
    }

    public function test_requires_some_tags(): void
    {
        $user = User::factory()->create();
        $outfit = $this->outfit($user, ['vay']);

        $this->withToken($user->issueApiToken())
            ->putJson("/api/outfits/{$outfit->id}/hashtags", ['add' => [], 'remove' => '  '])
            ->assertUnprocessable();
    }

    public function test_cannot_edit_someone_elses_outfit(): void
    {
        $owner = User::factory()->create();
        $outfit = $this->outfit($owner, ['vay']);
        $outfit->update(['is_public' => true]);

        $this->withToken(User::factory()->create()->issueApiToken())
            ->putJson("/api/outfits/{$outfit->id}/hashtags", ['add' => ['henho']])
            ->assertNotFound();

        $this->assertSame(['vay'], $outfit->hashtags()->pluck('name')->all());
    }

    public function test_requires_token(): void
    {
        $outfit = $this->outfit(User::factory()->create(), ['vay']);

        $this->putJson("/api/outfits/{$outfit->id}/hashtags", ['add' => ['henho']])->assertUnauthorized();
    }
}
