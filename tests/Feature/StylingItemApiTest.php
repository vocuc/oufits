<?php

namespace Tests\Feature;

use App\Models\ItemPair;
use App\Models\Outfit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Món đồ + độ hợp: /api/outfits/{id}/items, /api/outfit-items, /api/styling/learn|score|suggest, /api/item-pairs. */
class StylingItemApiTest extends TestCase
{
    use RefreshDatabase;

    private function item(string $slot, string $category, ?string $color = null, array $extra = []): array
    {
        return ['slot' => $slot, 'category' => $category, 'color' => $color, ...$extra];
    }

    /** Tạo outfit có hashtag + item. */
    private function outfit(User $user, array $tags, array $items): Outfit
    {
        $outfit = $user->outfits()->create(['title' => 'O', 'items' => '- a']);
        $outfit->syncHashtags($tags);
        foreach ($items as $i => $item) {
            $outfit->itemsList()->create([...$item, 'user_id' => $user->id, 'position' => $i]);
        }

        return $outfit;
    }

    /** Bộ dữ liệu: cardigan đi với chân váy xếp ly + mary jane; áo phao đi với quần legging + boot. */
    private function seedData(User $user): void
    {
        for ($k = 0; $k < 6; $k++) {
            $this->outfit($user, ['giaymaryjane', 'henho'], [
                $this->item('outer', 'cardigan', 'maudoruou', ['material' => 'len']),
                $this->item('bottom', 'chanvayxepli', 'maukem'),
                $this->item('socks', 'tattrang', 'mautrang'),
            ]);
            $this->outfit($user, ['giayboot', 'dulich'], [
                $this->item('outer', 'aophao', 'mauden'),
                $this->item('bottom', 'quanlegging', 'mauden'),
                $this->item('top', 'aolen', 'mauxam', ['material' => 'len']),
            ]);
        }
    }

    public function test_put_items_replaces_and_missing_lists_outfits_without_items(): void
    {
        $user = User::factory()->create();
        $token = $user->issueApiToken();
        $a = $user->outfits()->create(['title' => 'A', 'items' => '- a']);
        $b = $user->outfits()->create(['title' => 'B', 'items' => '- b']);

        $this->withToken($token)->putJson("/api/outfits/{$a->id}/items", ['items' => [
            $this->item('top', 'aolen', 'maukem', ['material' => 'lenvanthung', 'details' => ['no'], 'wear' => ['sovin'], 'raw' => 'Áo len kem']),
            $this->item('bottom', 'chanvayxepli', 'mauden'),
        ]])->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('data.0.details', ['no']);

        $this->withToken($token)->putJson("/api/outfits/{$a->id}/items", ['items' => [$this->item('dress', 'damhaiday', 'mauden')]])
            ->assertOk()->assertJsonCount(1, 'data');

        $this->withToken($token)->getJson('/api/outfit-items?missing=1')->assertOk()->assertJsonPath('data', [$b->id]);
        $this->withToken($token)->getJson('/api/outfit-items?slot=dress')->assertOk()->assertJsonPath('meta.total', 1);

        $this->withToken($token)->putJson("/api/outfits/{$a->id}/items", ['items' => [$this->item('legs', 'Áo Len')]])
            ->assertUnprocessable()->assertJsonValidationErrors(['items.0.slot', 'items.0.category']);

        $foreign = User::factory()->create()->outfits()->create(['title' => 'X', 'items' => '- x']);
        $this->withToken($token)->putJson("/api/outfits/{$foreign->id}/items", ['items' => []])->assertNotFound();
    }

    public function test_learn_builds_positive_and_negative_pairs(): void
    {
        $user = User::factory()->create();
        $this->seedData($user);
        $token = $user->issueApiToken();

        $this->withToken($token)->postJson('/api/styling/learn')->assertOk()->assertJsonPath('data.outfits', 12);

        $pos = ItemPair::query()->where(['level' => 'cat', 'a' => 'cardigan', 'b' => 'chanvayxepli'])->firstOrFail();
        $this->assertSame(6, $pos->count);
        $this->assertGreaterThan(0.3, $pos->score);

        $neg = ItemPair::query()->where(['level' => 'cat', 'a' => 'cardigan', 'b' => 'quanlegging'])->firstOrFail();
        $this->assertSame(0, $neg->count);
        $this->assertLessThan(-0.3, $neg->score);

        $this->assertTrue(ItemPair::query()->where(['level' => 'cat_shoe', 'a' => 'chanvayxepli', 'b' => 'giaymaryjane'])->exists());
        $this->assertTrue(ItemPair::query()->where(['level' => 'cat_ctx', 'a' => 'aophao', 'b' => 'dulich'])->exists());

        $hits = $this->withToken($token)->getJson('/api/item-pairs?level=cat&a=cardigan&min_score=0.3')->assertOk()->json('data');
        $this->assertContains('chanvayxepli', array_column($hits, 'b'));
        $this->assertNotContains('quanlegging', array_column($hits, 'b'));
        $this->withToken($token)->getJson('/api/item-pairs?level=cat&a=cardigan&max_score=-0.3')
            ->assertOk()->assertJsonPath('data.0.score', $neg->score);
    }

    public function test_score_ranks_matching_set_above_mismatched_and_flags_structure(): void
    {
        $user = User::factory()->create();
        $this->seedData($user);
        $token = $user->issueApiToken();
        $this->withToken($token)->postJson('/api/styling/learn')->assertOk();

        $good = $this->withToken($token)->postJson('/api/styling/score', [
            'items' => [$this->item('outer', 'cardigan', 'maudoruou', ['material' => 'len']), $this->item('bottom', 'chanvayxepli', 'maukem'), $this->item('top', 'aothun', 'mautrang')],
            'shoe' => 'giaymaryjane', 'context' => ['henho'],
        ])->assertOk()->json('data');
        $bad = $this->withToken($token)->postJson('/api/styling/score', [
            'items' => [$this->item('outer', 'cardigan', 'maudoruou'), $this->item('bottom', 'quanlegging', 'mauden'), $this->item('top', 'aothun', 'mautrang')],
            'shoe' => 'giaymaryjane', 'context' => ['henho'],
        ])->assertOk()->json('data');
        $this->assertGreaterThan($bad['score'], $good['score']);

        $this->withToken($token)->postJson('/api/styling/score', ['items' => [
            $this->item('dress', 'damhaiday', 'mauden'), $this->item('bottom', 'quanshort', 'mauden'),
        ]])->assertOk()->assertJsonCount(1, 'data.issues');
    }

    public function test_suggest_returns_valid_sets_for_shoe(): void
    {
        $user = User::factory()->create();
        $this->seedData($user);
        $token = $user->issueApiToken();
        $this->withToken($token)->postJson('/api/styling/learn')->assertOk();

        $data = $this->withToken($token)->postJson('/api/styling/suggest', [
            'shoe' => 'giaymaryjane', 'context' => ['henho'], 'n' => 3, 'exclude_outfit_ids' => [],
        ])->assertOk()->json('data');

        $this->assertNotEmpty($data);
        $first = collect($data[0]['items'])->pluck('category')->all();
        $this->assertContains('chanvayxepli', $first);
        foreach ($data as $s) {
            $slots = collect($s['items'])->pluck('slot');
            $this->assertTrue($slots->contains('dress') || ($slots->contains('top') && $slots->contains('bottom')));
        }
    }
}
