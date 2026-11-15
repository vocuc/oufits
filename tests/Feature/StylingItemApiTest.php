<?php

namespace Tests\Feature;

use App\Models\ItemPair;
use App\Models\Outfit;
use App\Models\OutfitRender;
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

    /** Điểm chủ shop chấm ảnh trên Thư viện ảnh -> cấp fb / fb_shoe, kéo điểm bộ món giống outfit đó lên / xuống. */
    public function test_shop_ratings_are_learned_into_score(): void
    {
        $user = User::factory()->create();
        $token = $user->issueApiToken();
        $this->seedData($user);
        $liked = [$this->item('top', 'aolen', 'mauxam'), $this->item('bottom', 'quanjean', 'mauxanh')];
        $disliked = [$this->item('top', 'aocroptop', 'maudo'), $this->item('bottom', 'chanvaymini', 'mauvang')];
        $a = $this->outfit($user, ['giaysandal'], $liked);
        $b = $this->outfit($user, ['giaysandal'], $disliked);
        $this->withToken($token)->postJson('/api/styling/learn')->assertOk();
        $score = fn (array $items) => $this->withToken($token)
            ->postJson('/api/styling/score', ['items' => $items, 'shoe' => 'giaysandal'])->assertOk()->json('data.score');
        [$likedBefore, $dislikedBefore] = [$score($liked), $score($disliked)];

        $renderA = OutfitRender::query()->create(['user_id' => $user->id, 'outfit_id' => $a->id, 'image_path' => 'renders/a.png']);
        $renderB = OutfitRender::query()->create(['user_id' => $user->id, 'outfit_id' => $b->id, 'image_path' => 'renders/b.png']);
        $this->actingAs($user)->put(route('renders.rate', $renderA), ['rating' => 10])->assertRedirect();
        $this->put(route('renders.rate', $renderB), ['rating' => 1])->assertRedirect();
        $this->assertSame(10, $renderA->fresh()->rating);
        $this->assertNotNull($renderA->fresh()->rated_at);

        // Học ngay sau khi chấm (không cần gọi learn)
        $fb = ItemPair::query()->where('level', 'fb')->where('a', 'aolen:mauxam')->where('b', 'quanjean:mauxanh')->firstOrFail();
        $this->assertSame(0.5, $fb->score);
        $this->assertTrue(ItemPair::query()->where('level', 'fb_shoe')->where('a', 'chanvaymini:mauvang')->where('b', 'giaysandal')->where('score', '<', 0)->exists());
        $this->assertGreaterThan($likedBefore, $score($liked));
        $this->assertLessThan($dislikedBefore, $score($disliked));

        // learn() tính lại toàn bộ vẫn giữ phần điểm chấm
        $this->withToken($token)->postJson('/api/styling/learn')->assertOk()->assertJsonPath('data.pairs.fb', 2);

        // Bỏ điểm -> hết ảnh hưởng
        $this->actingAs($user)->put(route('renders.rate', $renderA), ['rating' => ''])->assertRedirect();
        $this->put(route('renders.rate', $renderB), ['rating' => ''])->assertRedirect();
        $this->assertNull($renderA->fresh()->rating);
        $this->assertSame(0, ItemPair::query()->whereIn('level', ['fb', 'fb_shoe'])->count());
        $this->assertSame($likedBefore, $score($liked));
    }

    public function test_rating_validation_and_owner_only(): void
    {
        $user = User::factory()->create();
        $outfit = $user->outfits()->create(['title' => 'A', 'items' => '- a']);
        $render = OutfitRender::query()->create(['user_id' => $user->id, 'outfit_id' => $outfit->id, 'image_path' => 'renders/a.png']);

        foreach ([0, 11, 'abc'] as $bad) {
            $this->actingAs($user)->putJson(route('renders.rate', $render), ['rating' => $bad])->assertUnprocessable();
        }
        $this->actingAs($user)->putJson(route('renders.rate', $render), ['rating' => 7])->assertOk()->assertJsonPath('data.rating', 7);
        $this->actingAs(User::factory()->create())->putJson(route('renders.rate', $render), ['rating' => 1])->assertNotFound();
        $this->assertSame(7, $render->fresh()->rating);

        // Trang Thư viện ảnh: hiện điểm + lọc chưa chấm / đã chấm
        $this->actingAs($user)->get('/renders')->assertOk()->assertSee('7/10');
        $this->actingAs($user)->get('/renders?rated=0')->assertOk()->assertSee('Chưa có ảnh nào.');
        $this->actingAs($user)->get('/renders?rated=1')->assertOk()->assertSee('7/10');
    }
}
