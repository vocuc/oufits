<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OutfitApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_rejects_missing_or_wrong_token(): void
    {
        User::factory()->create()->issueApiToken();

        $this->postJson('/api/outfits', [])->assertUnauthorized();
        $this->withToken('wrong')->postJson('/api/outfits', [])->assertUnauthorized();
    }

    public function test_creates_outfit_for_token_owner_with_hashtag_string(): void
    {
        $user = User::factory()->create();
        $token = $user->issueApiToken();

        $this->withToken($token)->postJson('/api/outfits', [
            'title' => 'Len đen trễ vai',
            'items' => '- Áo len đen trễ vai',
            'hashtags' => 'thudong #Màu_Đen, giaycaogot',
        ])->assertCreated()
            ->assertJsonPath('data.title', 'Len đen trễ vai')
            ->assertJsonPath('data.hashtags', ['giaycaogot', 'mauden', 'thudong']);

        $this->assertSame(1, $user->outfits()->count());
    }

    public function test_creates_outfit_with_hashtag_array(): void
    {
        $token = User::factory()->create()->issueApiToken();

        $this->withToken($token)->postJson('/api/outfits', [
            'title' => 'Đầm nhung',
            'items' => '- Đầm nhung đen',
            'hashtags' => ['dutiec', 'vay'],
        ])->assertCreated()->assertJsonPath('data.hashtags', ['dutiec', 'vay']);
    }

    public function test_lists_only_own_outfits(): void
    {
        $me = User::factory()->create();
        $other = User::factory()->create();
        $me->outfits()->create(['title' => 'Của tôi', 'items' => '- a']);
        $other->outfits()->create(['title' => 'Của người khác', 'items' => '- b']);

        $this->withToken($me->issueApiToken())->getJson('/api/outfits')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Của tôi');
    }

    public function test_reissuing_token_invalidates_old_one(): void
    {
        $user = User::factory()->create();
        $old = $user->issueApiToken();
        $new = $user->issueApiToken();

        $this->withToken($old)->getJson('/api/outfits')->assertUnauthorized();
        $this->withToken($new)->getJson('/api/outfits')->assertOk();
    }

    public function test_validates_required_fields(): void
    {
        $token = User::factory()->create()->issueApiToken();

        $this->withToken($token)->postJson('/api/outfits', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title', 'items', 'hashtags']);
    }
}
