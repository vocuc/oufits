<?php

namespace Tests\Feature;

use App\Models\StylingIdea;
use App\Models\StylingRule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Kho kiến thức phối đồ: /api/styling-rules, /api/styling-ideas (Bearer token OOTD). */
class StylingApiTest extends TestCase
{
    use RefreshDatabase;

    private function rule(array $override = []): array
    {
        return [
            'shoe_type' => 'giaysneaker',
            'dimension' => 'bottom',
            'value' => 'quanongrong',
            'relation' => 'best',
            'score' => 0.56,
            'evidence_count' => 15,
            'note' => 'Sneaker + quần ống rộng',
            'examples' => [['ootd_id' => 279]],
            'source' => 'ootd_stats',
            ...$override,
        ];
    }

    private function idea(array $override = []): array
    {
        return [
            'source_key' => 'tiktok:7699878033842867476:3',
            'title' => 'Sneaker trắng + jeans ống rộng + cardigan đỏ đô',
            'idea' => 'Điểm nhấn đỏ đô trên nền denim, trẻ trung mà chỉn chu.',
            'how_to' => "- Cardigan len đỏ đô\n- Áo ba lỗ trắng\n- Jeans ống rộng xanh nhạt\n- Sneaker trắng",
            'shoe_types' => ['giaysneaker'],
            'occasions' => ['dichoi', 'dicafe'],
            'seasons' => ['xuanthu'],
            'styles' => ['nangdong'],
            'colors' => ['maudo', 'mauxanhdenim'],
            'tags' => ['cardigan', 'quanjeans'],
            'age_range' => '18-25',
            'source' => 'tiktok',
            'source_url' => 'https://www.tiktok.com/@miumiuu5/photo/7699878033842867476',
            'source_author' => 'miumiuu5',
            'image_ref' => 'miumiuu5_7699878033842867476_03.jpg',
            'rating' => 4,
            ...$override,
        ];
    }

    public function test_requires_token(): void
    {
        $this->getJson('/api/styling-rules')->assertUnauthorized();
        $this->getJson('/api/styling-ideas')->assertUnauthorized();
    }

    public function test_rules_upsert_creates_then_updates(): void
    {
        $user = User::factory()->create();
        $token = $user->issueApiToken();

        $this->withToken($token)->putJson('/api/styling-rules', ['rules' => [
            $this->rule(),
            $this->rule(['shoe_type' => null, 'dimension' => 'other', 'value' => 'chung', 'relation' => 'good']),
            $this->rule(['source' => 'tiktok']),
        ]])->assertOk()->assertJsonPath('data', ['created' => 3, 'updated' => 0]);

        $this->withToken($token)->putJson('/api/styling-rules', ['rules' => [
            $this->rule(['score' => 0.6]),
            $this->rule(['shoe_type' => null, 'dimension' => 'other', 'value' => 'chung', 'relation' => 'good']),
        ]])->assertOk()->assertJsonPath('data', ['created' => 0, 'updated' => 2]);

        $this->assertSame(3, $user->stylingRules()->count());
        $this->assertSame(0.6, $user->stylingRules()->where('source', 'ootd_stats')->where('shoe_type', 'giaysneaker')->first()->score);
    }

    public function test_rules_index_filters_and_is_per_account(): void
    {
        $user = User::factory()->create();
        $user->stylingRules()->create($this->rule());
        $user->stylingRules()->create($this->rule(['dimension' => 'occasion', 'value' => 'henho', 'relation' => 'good', 'score' => 0.15]));
        $user->stylingRules()->create($this->rule(['shoe_type' => 'giayboot', 'value' => 'chanvay']));
        $user->stylingRules()->create($this->rule(['shoe_type' => null, 'dimension' => 'other', 'value' => 'chung']));
        User::factory()->create()->stylingRules()->create($this->rule());
        $token = $user->issueApiToken();

        $this->withToken($token)->getJson('/api/styling-rules?shoe_type=giaysneaker')->assertOk()->assertJsonCount(3, 'data');
        $this->withToken($token)->getJson('/api/styling-rules?shoe_type=giaysneaker&with_general=0&min_score=0.3')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.value', 'quanongrong')
            ->assertJsonMissingPath('data.0.shoe_key');
        $this->withToken($token)->getJson('/api/styling-rules?dimension=bottom')->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_rules_user_source_is_protected_and_invalid_values_rejected(): void
    {
        $user = User::factory()->create();
        $token = $user->issueApiToken();

        $this->withToken($token)->putJson('/api/styling-rules', ['rules' => [
            $this->rule(['source' => 'user', 'dimension' => 'mood', 'value' => 'Quần Ống', 'relation' => 'ok', 'score' => 2]),
        ]])->assertUnprocessable()->assertJsonValidationErrors([
            'rules.0.source', 'rules.0.dimension', 'rules.0.value', 'rules.0.relation', 'rules.0.score',
        ]);

        $mine = $user->stylingRules()->create($this->rule(['source' => 'user']));
        $this->withToken($token)->deleteJson("/api/styling-rules/{$mine->id}")->assertStatus(409);
        $other = $user->stylingRules()->create($this->rule());
        $this->withToken($token)->deleteJson("/api/styling-rules/{$other->id}")->assertNoContent();
        $this->assertModelMissing($other);

        $foreign = User::factory()->create()->stylingRules()->create($this->rule());
        $this->withToken($token)->deleteJson("/api/styling-rules/{$foreign->id}")->assertNotFound();
    }

    public function test_ideas_upsert_keys_and_confirmed_protection(): void
    {
        $user = User::factory()->create();
        $token = $user->issueApiToken();
        $outfit = $user->outfits()->create(['title' => 'O', 'items' => '- a']);

        $this->withToken($token)->putJson('/api/styling-ideas', ['ideas' => [
            $this->idea(['outfit_id' => $outfit->id]),
            $this->idea(['source_key' => 'tiktok:1:1']),
        ]])->assertOk()->assertJsonPath('data', ['created' => 2, 'updated' => 0, 'skipped' => 0]);

        $this->withToken($token)->putJson('/api/styling-ideas', ['ideas' => [$this->idea(['rating' => 5])]])
            ->assertOk()->assertJsonPath('data', ['created' => 0, 'updated' => 1, 'skipped' => 0]);
        $this->withToken($token)->getJson('/api/styling-ideas/keys?source=tiktok')
            ->assertOk()->assertJsonPath('data', ['tiktok:7699878033842867476:3', 'tiktok:1:1']);

        StylingIdea::query()->where('source_key', 'tiktok:1:1')->update(['is_confirmed' => true]);
        $this->withToken($token)->putJson('/api/styling-ideas', ['ideas' => [$this->idea(['source_key' => 'tiktok:1:1', 'title' => 'Khác'])]])
            ->assertOk()->assertJsonPath('data.skipped', 1);
        $confirmed = StylingIdea::query()->where('source_key', 'tiktok:1:1')->firstOrFail();
        $this->assertNotSame('Khác', $confirmed->title);
        $this->withToken($token)->deleteJson("/api/styling-ideas/{$confirmed->id}")->assertStatus(409);

        $foreignOutfit = User::factory()->create()->outfits()->create(['title' => 'X', 'items' => '- b']);
        $this->withToken($token)->putJson('/api/styling-ideas', ['ideas' => [
            $this->idea(['source_key' => 'x', 'outfit_id' => $foreignOutfit->id, 'source' => 'user', 'shoe_types' => ['Giày'], 'age_range' => 'teen']),
        ]])->assertUnprocessable()->assertJsonValidationErrors([
            'ideas.0.outfit_id', 'ideas.0.source', 'ideas.0.shoe_types.0', 'ideas.0.age_range',
        ]);
    }

    public function test_ideas_filters_and_used(): void
    {
        $user = User::factory()->create();
        $user->stylingIdeas()->create($this->idea());
        $user->stylingIdeas()->create($this->idea(['source_key' => 'a', 'shoe_types' => ['giayboot'], 'occasions' => ['henho'], 'rating' => 5]));
        $user->stylingIdeas()->create($this->idea(['source_key' => 'b', 'shoe_types' => ['giayboot', 'giaysneaker'], 'occasions' => ['henho'], 'rating' => 2]));
        User::factory()->create()->stylingIdeas()->create($this->idea());
        $token = $user->issueApiToken();

        $this->withToken($token)->getJson('/api/styling-ideas?shoe_types=giaysneaker')->assertOk()->assertJsonPath('meta.total', 2);
        $this->withToken($token)->getJson('/api/styling-ideas?shoe_types=giaysneaker&occasions=henho')
            ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.source_key', 'b');
        $this->withToken($token)->getJson('/api/styling-ideas?occasions=henho,dicafe&min_rating=4')->assertOk()->assertJsonPath('meta.total', 2);
        $this->withToken($token)->getJson('/api/styling-ideas?q=cardigan')->assertOk()->assertJsonPath('meta.total', 3);

        $id = $user->stylingIdeas()->where('source_key', 'a')->value('id');
        $this->withToken($token)->postJson("/api/styling-ideas/{$id}/used")->assertOk()->assertJsonPath('data.times_used', 1);
        $this->withToken($token)->getJson('/api/styling-ideas?order=fresh&shoe_types=giayboot')
            ->assertOk()->assertJsonPath('data.0.source_key', 'b');
    }
}
