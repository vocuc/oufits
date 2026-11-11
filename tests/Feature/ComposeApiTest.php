<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\PromptComposer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ComposeApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_requires_token(): void
    {
        $this->getJson('/api/compose?ids[]=1')->assertUnauthorized();
    }

    public function test_single_outfit_uses_account_common_and_fix_prompt(): void
    {
        $user = User::factory()->create(['common_prompt' => 'PHẦN CHUNG RIÊNG', 'fix_prompt' => 'SỬA RIÊNG']);
        $outfit = $user->outfits()->create(['title' => 'Len đen', 'style' => 'Street style', 'items' => '- Áo len đen']);

        $res = $this->withToken($user->issueApiToken())->getJson('/api/compose?ids[]='.$outfit->id)
            ->assertOk()
            ->assertJsonPath('data.outfit_ids', [$outfit->id])
            ->assertJsonPath('data.fix', 'SỬA RIÊNG');

        $prompt = $res->json('data.prompt');
        $this->assertSame(PromptComposer::compose(collect([$outfit]), 'PHẦN CHUNG RIÊNG'), $prompt);
        $this->assertStringContainsString('Tạo MỘT ảnh', $prompt);
        $this->assertStringContainsString('OUTFIT – Len đen', $prompt);
    }

    public function test_falls_back_to_default_prompts(): void
    {
        $user = User::factory()->create();
        $outfit = $user->outfits()->create(['title' => 'A', 'items' => '- a']);

        $this->withToken($user->issueApiToken())->getJson('/api/compose?ids[]='.$outfit->id)
            ->assertOk()
            ->assertJsonPath('data.fix', PromptComposer::defaultFix())
            ->assertJsonPath('data.prompt', PromptComposer::compose(collect([$outfit]), PromptComposer::defaultCommon()));
    }

    public function test_keeps_order_of_ids(): void
    {
        $user = User::factory()->create();
        $a = $user->outfits()->create(['title' => 'Món A', 'items' => '- a']);
        $b = $user->outfits()->create(['title' => 'Món B', 'items' => '- b']);

        $prompt = $this->withToken($user->issueApiToken())->getJson("/api/compose?ids[]={$b->id}&ids[]={$a->id}")
            ->assertOk()
            ->assertJsonPath('data.outfit_ids', [$b->id, $a->id])
            ->json('data.prompt');

        $this->assertStringContainsString('OUTFIT 1 – Món B', $prompt);
        $this->assertStringContainsString('OUTFIT 2 – Món A', $prompt);
    }

    public function test_rejects_outfit_of_other_account_or_missing(): void
    {
        $me = User::factory()->create();
        $mine = $me->outfits()->create(['title' => 'Của tôi', 'items' => '- a']);
        $theirs = User::factory()->create()->outfits()->create(['title' => 'Của người khác', 'items' => '- b']);

        $this->withToken($me->issueApiToken())->getJson("/api/compose?ids[]={$mine->id}&ids[]={$theirs->id}&ids[]=99999")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['ids' => "Không tìm thấy outfit: {$theirs->id}, 99999."]);
    }

    public function test_requires_ids(): void
    {
        $token = User::factory()->create()->issueApiToken();

        $this->withToken($token)->getJson('/api/compose')->assertUnprocessable()->assertJsonValidationErrors('ids');
    }
}
