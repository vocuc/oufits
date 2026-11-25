<?php

namespace Tests\Feature;

use App\Models\Outfit;
use App\Models\StylingRule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Luật cấm phối (2026-11-25): trang /styling-rules + API outfit ?rules=1 [&shoe=] bỏ outfit vi phạm luật đã duyệt. */
class StylingConflictTest extends TestCase
{
    use RefreshDatabase;

    private function outfit(User $user, array $tags, bool $cover = true): Outfit
    {
        $outfit = $user->outfits()->create(['title' => implode(' ', $tags), 'items' => '- a']);
        $outfit->syncHashtags($tags);
        if ($cover) {
            $outfit->forceFill(['cover_path' => 'covers/'.$outfit->id.'.jpg'])->save();
        }

        return $outfit;
    }

    private function avoid(User $user, string $shoe, string $value, bool $confirmed = true, string $dimension = 'outer', string $source = 'user'): StylingRule
    {
        return $user->stylingRules()->create([
            'shoe_type' => $shoe, 'dimension' => $dimension, 'value' => $value, 'relation' => 'avoid',
            'source' => $source, 'is_confirmed' => $confirmed,
        ]);
    }

    /** @return int[] */
    private function ids(User $user, array $query): array
    {
        return collect($this->withToken($user->issueApiToken())->getJson('/api/outfits?'.http_build_query(['scope' => 'mine', ...$query]))
            ->assertOk()->json('data'))->pluck('id')->sort()->values()->all();
    }

    public function test_api_drops_outfits_breaking_confirmed_rules_only_when_asked(): void
    {
        $user = User::factory()->create();
        $bad = $this->outfit($user, ['depquai', 'aomangto', 'thudong']);
        $ok = $this->outfit($user, ['depquai', 'aolen', 'thudong']);
        $coat = $this->outfit($user, ['giaycaogot', 'aomangto', 'thudong']);
        $draft = $this->outfit($user, ['depkep', 'aokhoaclong']);
        $this->avoid($user, 'depquai', 'aomangto');
        $this->avoid($user, 'depkep', 'aokhoaclong', confirmed: false); // chưa duyệt -> không lọc
        $this->avoid($user, 'depquai', 'aolen', dimension: 'other'); // luật chữ -> không lọc

        // Mặc định không lọc (skill tạo outfit kiểm tra trùng)
        $this->assertSame([$bad->id, $ok->id, $coat->id, $draft->id], $this->ids($user, []));
        // rules=1: bỏ outfit tự mâu thuẫn (dép + măng tô); giày khác + măng tô vẫn giữ
        $this->assertSame([$ok->id, $coat->id, $draft->id], $this->ids($user, ['rules' => 1]));
        // shoe=depquai (lô theo sản phẩm là dép): bỏ cả outfit giày khác có măng tô
        $this->assertSame([$ok->id, $draft->id], $this->ids($user, ['rules' => 1, 'shoe' => 'depquai,depkep']));
        $this->withToken($user->issueApiToken())->getJson('/api/outfits?scope=mine&rules=1&tags=thudong')
            ->assertJsonPath('meta.total', 2);

        // Luật của tài khoản khác không áp cho mình
        $other = User::factory()->create();
        $this->avoid($other, 'depquai', 'aolen');
        $this->assertSame([$ok->id, $coat->id, $draft->id], $this->ids($user, ['rules' => 1]));
    }

    public function test_hashtag_counts_skip_conflicting_outfits_with_rules(): void
    {
        $user = User::factory()->create();
        $this->outfit($user, ['depquai', 'aomangto', 'thudong']);
        $this->outfit($user, ['depquai', 'thudong']);
        $this->outfit($user, ['giaycaogot', 'aomangto', 'thudong']);
        $this->avoid($user, 'depquai', 'aomangto');
        $api = $this->withToken($user->issueApiToken());

        $api->getJson('/api/outfit-hashtags/count?tags=thudong')->assertJsonPath('data.outfits', 3);
        $api->getJson('/api/outfit-hashtags/count?tags=thudong&rules=1')->assertJsonPath('data.outfits', 2);
        $api->getJson('/api/outfit-hashtags/count?tags=thudong&rules=1&shoe=depquai')->assertJsonPath('data.outfits', 1);

        $tags = collect($api->getJson('/api/outfit-hashtags?rules=1')->assertOk()->json('data'))->pluck('outfits', 'name');
        $this->assertSame(1, $tags['aomangto']);
        $this->assertSame(2, $tags['thudong']);
    }

    public function test_page_adds_confirmed_rules_per_shoe_and_item_and_toggles(): void
    {
        $user = User::factory()->create();
        $this->outfit($user, ['depquai', 'aomangto']);
        $claude = $this->avoid($user, 'depkep', 'aophao', confirmed: false, source: 'claude');

        $this->actingAs($user)->post('/styling-rules', [
            'shoes' => '#depquai, deplong', 'dimension' => 'outer', 'values' => 'aomangto, #AoKhoacDai', 'note' => 'Dép không phối áo khoác dài',
        ])->assertRedirect(route('styling-rules.index'));
        $rows = $user->stylingRules()->where('source', 'user')->get();
        $this->assertCount(4, $rows);
        $this->assertEqualsCanonicalizing(['depquai|aomangto', 'depquai|aokhoacdai', 'deplong|aomangto', 'deplong|aokhoacdai'],
            $rows->map(fn ($r) => $r->shoe_type.'|'.$r->value)->all());
        $this->assertTrue($rows->every(fn ($r) => $r->is_confirmed && $r->relation === 'avoid'));

        $this->actingAs($user)->get('/styling-rules')->assertOk()->assertSee('Luật cấm phối')->assertSee('1 outfit vi phạm');

        // Duyệt luật Claude soạn -> có hiệu lực; luật Claude không xoá được, chỉ bỏ duyệt
        $this->actingAs($user)->put("/styling-rules/{$claude->id}", ['is_confirmed' => 1])->assertRedirect();
        $this->assertTrue($claude->fresh()->is_confirmed);
        $this->actingAs($user)->delete("/styling-rules/{$claude->id}")->assertForbidden();
        $this->actingAs($user)->delete("/styling-rules/{$rows[0]->id}")->assertRedirect();
        $this->assertCount(3, $user->stylingRules()->where('source', 'user')->get());

        // Luật của người khác -> 404
        $this->actingAs(User::factory()->create())->put("/styling-rules/{$claude->id}", ['is_confirmed' => 0])->assertNotFound();
    }
}
