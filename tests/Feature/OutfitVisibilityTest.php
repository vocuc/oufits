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

    public function test_api_list_returns_mine_and_others_public_by_default(): void
    {
        $me = User::factory()->create();
        $token = $me->issueApiToken();
        $other = User::factory()->create(['name' => 'Người khác']);
        $this->outfitFor($me, ['title' => 'Của tôi riêng tư', 'is_public' => false]);
        $this->outfitFor($other, ['title' => 'Họ công khai']);
        $this->outfitFor($other, ['title' => 'Họ riêng tư', 'is_public' => false]);

        $all = $this->withToken($token)->getJson('/api/outfits')->assertOk()->json('data');
        $titles = collect($all)->pluck('title')->sort()->values()->all();
        $this->assertSame(['Của tôi riêng tư', 'Họ công khai'], $titles);
        $theirs = collect($all)->firstWhere('title', 'Họ công khai');
        $this->assertFalse($theirs['is_mine']);
        $this->assertSame('Người khác', $theirs['owner']);

        $this->withToken($token)->getJson('/api/outfits?scope=mine')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.title', 'Của tôi riêng tư');
    }

    public function test_api_list_filters_by_all_tags(): void
    {
        $me = User::factory()->create();
        $token = $me->issueApiToken();
        $this->outfitFor($me, ['title' => 'A'])->syncHashtags('thudong giayboot');
        $this->outfitFor(User::factory()->create(), ['title' => 'B'])->syncHashtags('thudong giayboot vay');
        $this->outfitFor($me, ['title' => 'C'])->syncHashtags('thudong');

        $titles = collect($this->withToken($token)->getJson('/api/outfits?tags=thudong,%23GiayBoot')
            ->assertOk()->json('data'))->pluck('title')->sort()->values()->all();

        $this->assertSame(['A', 'B'], $titles);
    }

    public function test_api_list_paginates_with_limit_offset(): void
    {
        $me = User::factory()->create();
        $token = $me->issueApiToken();
        foreach (range(1, 5) as $i) {
            $this->outfitFor($me, ['title' => "O$i"]);
        }

        $this->withToken($token)->getJson('/api/outfits?limit=2')
            ->assertOk()
            ->assertJsonPath('data.*.title', ['O5', 'O4'])
            ->assertJsonPath('meta', ['total' => 5, 'limit' => 2, 'offset' => 0, 'next_offset' => 2]);

        $this->withToken($token)->getJson('/api/outfits?limit=2&offset=4')
            ->assertOk()
            ->assertJsonPath('data.*.title', ['O1'])
            ->assertJsonPath('meta.next_offset', null);

        $this->withToken($token)->getJson('/api/outfits?limit=500')->assertUnprocessable();
        $this->withToken($token)->getJson('/api/outfits?offset=-1')->assertUnprocessable();
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
