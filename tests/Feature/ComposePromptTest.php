<?php

namespace Tests\Feature;

use App\Models\OutfitLayout;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ComposePromptTest extends TestCase
{
    use RefreshDatabase;

    private function approve(User $user, string $code, string $prompt, int $sort = 0): void
    {
        $user->outfitLayouts()->create(['code' => $code, 'name' => "Layout $code", 'status' => 'approved',
            'prompt' => $prompt, 'sort' => $sort]);
    }

    public function test_composes_layout_part_and_numbered_outfits_in_selected_order(): void
    {
        $user = User::factory()->create();
        $this->approve($user, '01', 'GIÀY (QUAN TRỌNG NHẤT) + chữ "{HOOK}"');
        $a = $user->outfits()->create(['title' => 'Len đen', 'style' => 'Street', 'items' => '- Áo len đen']);
        $b = $user->outfits()->create(['title' => 'Đầm nhung', 'items' => '- Đầm nhung đen']);

        $prompt = $this->actingAs($user)
            ->get(route('outfits.compose', ['ids' => [$b->id, $a->id]]))
            ->assertOk()->viewData('prompt');

        $this->assertStringContainsString('Tạo 2 ảnh RIÊNG BIỆT', $prompt);
        $this->assertStringContainsString('GIÀY (QUAN TRỌNG NHẤT', $prompt);
        $this->assertStringContainsString('chữ "Mặc xinh mỗi ngày"', $prompt);
        $this->assertLessThan(strpos($prompt, 'OUTFIT 2 – Len đen'), strpos($prompt, 'OUTFIT 1 – Đầm nhung'));
        $this->assertStringContainsString("Phong cách: Street\n- Áo len đen", $prompt);
    }

    public function test_choose_layout_and_error_without_approved_layout(): void
    {
        $user = User::factory()->create();
        $a = $user->outfits()->create(['title' => 'Len đen', 'items' => '- Áo len đen']);

        $this->actingAs($user)->get(route('outfits.compose', ['ids' => [$a->id]]))->assertOk()
            ->assertSee(OutfitLayout::NO_APPROVED)->assertViewHas('prompt', '');

        $this->approve($user, '01', 'KHUNG MỘT', 1);
        $this->approve($user, '02', 'KHUNG HAI', 2);
        $prompt = $this->actingAs($user)->get(route('outfits.compose', ['ids' => [$a->id], 'layout' => '02']))
            ->assertOk()->assertSee('01 · Layout 01')->viewData('prompt');
        $this->assertStringContainsString('KHUNG HAI', $prompt);
        $this->assertStringNotContainsString('KHUNG MỘT', $prompt);
        $this->assertStringNotContainsString('RIÊNG BIỆT', $prompt);
        $this->assertStringContainsString('OUTFIT – Len đen', $prompt);
    }

    public function test_cannot_compose_other_users_private_outfits(): void
    {
        $me = User::factory()->create();
        $theirs = User::factory()->create()->outfits()->create(['title' => 'Bí mật', 'items' => '- x', 'is_public' => false]);

        $this->actingAs($me)->get(route('outfits.compose', ['ids' => [$theirs->id]]))
            ->assertOk()->assertDontSee('Bí mật')->assertSee('Chưa chọn outfit');
    }

    public function test_can_compose_other_users_public_outfits(): void
    {
        $me = User::factory()->create();
        $theirs = User::factory()->create()->outfits()->create(['title' => 'Công khai', 'items' => '- x']);
        $this->approve($me, '01', 'KHUNG');

        $prompt = $this->actingAs($me)->get(route('outfits.compose', ['ids' => [$theirs->id]]))->viewData('prompt');

        $this->assertStringContainsString('OUTFIT – Công khai', $prompt);
    }

    public function test_pages_render(): void
    {
        $user = User::factory()->create();
        $a = $user->outfits()->create(['title' => 'Len đen', 'items' => '- Áo len đen']);
        $a->syncHashtags('thudong');

        $this->actingAs($user);
        $this->get('/')->assertOk()->assertSee('Chọn để ghép')->assertSee('#thudong');
        $this->get(route('outfits.show', $a))->assertOk()->assertSee('- Áo len đen');
        $this->get(route('outfits.create'))->assertOk();
        $this->get(route('settings.edit'))->assertOk()->assertSee('Mẫu ảnh outfit');
    }
}
