<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Danh sách hashtag outfit cho lệnh /lap-ke-hoach (A51) chọn hashtag chủ đề bài lookbook. */
class OutfitHashtagListApiTest extends TestCase
{
    use RefreshDatabase;

    private function outfit(User $user, string $tags, bool $cover = true): void
    {
        $outfit = $user->outfits()->create(['title' => 'O '.uniqid(), 'items' => '- a']);
        $outfit->forceFill(['cover_path' => $cover ? 'covers/x.jpg' : null])->save();
        $outfit->syncHashtags($tags);
    }

    public function test_lists_own_hashtags_counting_only_outfits_with_cover(): void
    {
        $me = User::factory()->create();
        $token = $me->issueApiToken();
        $this->outfit($me, 'henho sangchanh vay');
        $this->outfit($me, 'henho dutiec');
        $this->outfit($me, 'henho thudong', cover: false);
        $this->outfit(User::factory()->create(), 'henho henho2');

        $this->withToken($token)->getJson('/api/outfit-hashtags')
            ->assertOk()
            ->assertJsonPath('data.0', ['name' => 'henho', 'outfits' => 2])
            ->assertJsonCount(4, 'data')
            ->assertJsonPath('meta.outfits', 2);

        $this->withToken($token)->getJson('/api/outfit-hashtags?min=2')->assertJsonCount(1, 'data');
        $this->withToken($token)->getJson('/api/outfit-hashtags?with_cover=0')
            ->assertJsonPath('data.0', ['name' => 'henho', 'outfits' => 3]);
    }

    public function test_count_outfits_having_all_tags(): void
    {
        $me = User::factory()->create();
        $token = $me->issueApiToken();
        $this->outfit($me, 'henho sangchanh');
        $this->outfit($me, 'henho dutiec');

        $this->withToken($token)->getJson('/api/outfit-hashtags/count?tags=Hẹn hò,sangchanh')
            ->assertOk()
            ->assertJsonPath('data.tags', ['henho', 'sangchanh'])
            ->assertJsonPath('data.outfits', 1);
    }

    public function test_requires_token(): void
    {
        $this->getJson('/api/outfit-hashtags')->assertUnauthorized();
    }
}
