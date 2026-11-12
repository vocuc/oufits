<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ComposePromptTest extends TestCase
{
    use RefreshDatabase;

    public function test_composes_common_part_and_numbered_outfits_in_selected_order(): void
    {
        $user = User::factory()->create();
        $a = $user->outfits()->create(['title' => 'Len đen', 'style' => 'Street', 'items' => '- Áo len đen']);
        $b = $user->outfits()->create(['title' => 'Đầm nhung', 'items' => '- Đầm nhung đen']);

        $prompt = $this->actingAs($user)
            ->get(route('outfits.compose', ['ids' => [$b->id, $a->id]]))
            ->assertOk()->viewData('prompt');

        $this->assertStringContainsString('Tạo 2 ảnh RIÊNG BIỆT', $prompt);
        $this->assertStringContainsString('GIÀY (QUAN TRỌNG NHẤT', $prompt);
        $this->assertLessThan(strpos($prompt, 'OUTFIT 2 – Len đen'), strpos($prompt, 'OUTFIT 1 – Đầm nhung'));
        $this->assertStringContainsString("Phong cách: Street\n- Áo len đen", $prompt);
    }

    public function test_uses_the_accounts_own_common_prompt(): void
    {
        $user = User::factory()->create(['common_prompt' => 'PHẦN CHUNG CỦA TÔI']);
        $a = $user->outfits()->create(['title' => 'Len đen', 'items' => '- Áo len đen']);

        $prompt = $this->actingAs($user)->get(route('outfits.compose', ['ids' => [$a->id]]))->viewData('prompt');

        $this->assertStringContainsString('PHẦN CHUNG CỦA TÔI', $prompt);
        $this->assertStringNotContainsString('GIÀY (QUAN TRỌNG NHẤT', $prompt);
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
        $this->get(route('settings.edit'))->assertOk()->assertSee('GIÀY (QUAN TRỌNG NHẤT');
    }
}
