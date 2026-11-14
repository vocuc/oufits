<?php

namespace Tests\Feature;

use App\Models\OutfitLayout;
use App\Models\User;
use App\Support\PromptComposer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** GET /api/compose: ghép theo layout ĐÃ DUYỆT (Mẫu ảnh outfit), không còn prompt dùng chung. */
class ComposeApiTest extends TestCase
{
    use RefreshDatabase;

    private function layout(User $user, string $code, string $status = 'approved', ?string $prompt = 'KHUNG {CODE}: chữ "{HOOK}"', int $sort = 0): OutfitLayout
    {
        return $user->outfitLayouts()->create(['code' => $code, 'name' => "Layout $code", 'status' => $status,
            'prompt' => $prompt ? str_replace('{CODE}', $code, $prompt) : null, 'sort' => $sort]);
    }

    public function test_requires_token(): void
    {
        $this->getJson('/api/compose?ids[]=1')->assertUnauthorized();
    }

    public function test_error_when_no_approved_layout(): void
    {
        $user = User::factory()->create(['fix_prompt' => 'SỬA RIÊNG']);
        $outfit = $user->outfits()->create(['title' => 'Len đen', 'items' => '- Áo len đen']);
        $this->layout($user, '01', 'pending');
        $this->layout($user, '02', 'rejected');
        $this->layout($user, '03', 'approved', null); // duyệt nhưng chưa có prompt -> không dùng được
        $token = $user->issueApiToken();

        $this->withToken($token)->getJson('/api/compose?ids[]='.$outfit->id)
            ->assertUnprocessable()->assertJsonValidationErrors(['layout' => OutfitLayout::NO_APPROVED]);
    }

    public function test_uses_first_approved_layout_with_hook_and_fix(): void
    {
        $user = User::factory()->create(['fix_prompt' => 'SỬA RIÊNG']);
        $outfit = $user->outfits()->create(['title' => 'Len đen', 'style' => 'Street style', 'items' => '- Áo len đen']);
        $this->layout($user, '01', 'pending', sort: 1);
        $this->layout($user, '02', sort: 2);
        $this->layout($user, '03', sort: 3);

        $res = $this->withToken($user->issueApiToken())
            ->getJson('/api/compose?ids[]='.$outfit->id.'&hook='.urlencode('Đi chơi "xinh" nè'))
            ->assertOk()->assertJsonPath('data.layout', '02')->assertJsonPath('data.fix', 'SỬA RIÊNG');

        $prompt = $res->json('data.prompt');
        $this->assertSame(PromptComposer::compose(collect([$outfit]), 'KHUNG 02: chữ "{HOOK}"', 'Đi chơi "xinh" nè'), $prompt);
        $this->assertStringContainsString("KHUNG 02: chữ \"Đi chơi 'xinh' nè\"", $prompt);
        $this->assertStringNotContainsString('{HOOK}', $prompt);
        $this->assertStringContainsString('OUTFIT – Len đen', $prompt);
    }

    public function test_pick_layout_by_code_and_reject_unapproved_code(): void
    {
        $user = User::factory()->create();
        $outfit = $user->outfits()->create(['title' => 'A', 'items' => '- a']);
        $this->layout($user, '02');
        $this->layout($user, '04');
        $this->layout($user, '05', 'pending');
        $token = $user->issueApiToken();

        $this->withToken($token)->getJson("/api/compose?ids[]={$outfit->id}&layout=04")->assertOk()
            ->assertJsonPath('data.layout', '04');
        $this->withToken($token)->getJson("/api/compose?ids[]={$outfit->id}&layout=05")->assertUnprocessable()
            ->assertJsonValidationErrors(['layout' => 'Layout 05 chưa được duyệt hoặc chưa có prompt.']);
        // layout của tài khoản khác không dùng được
        $other = User::factory()->create();
        $this->layout($other, '09');
        $this->withToken($token)->getJson("/api/compose?ids[]={$outfit->id}&layout=09")->assertUnprocessable();
    }

    public function test_keeps_order_of_ids(): void
    {
        $user = User::factory()->create();
        $this->layout($user, '01');
        $a = $user->outfits()->create(['title' => 'Món A', 'items' => '- a']);
        $b = $user->outfits()->create(['title' => 'Món B', 'items' => '- b']);

        $prompt = $this->withToken($user->issueApiToken())->getJson("/api/compose?ids[]={$b->id}&ids[]={$a->id}")
            ->assertOk()->assertJsonPath('data.outfit_ids', [$b->id, $a->id])->json('data.prompt');

        $this->assertStringContainsString('OUTFIT 1 – Món B', $prompt);
        $this->assertStringContainsString('OUTFIT 2 – Món A', $prompt);
    }

    public function test_rejects_outfit_of_other_account_or_missing(): void
    {
        $me = User::factory()->create();
        $this->layout($me, '01');
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
