<?php

namespace Tests\Feature;

use App\Models\Outfit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/')->assertRedirect(route('login'));
        $this->get(route('settings.edit'))->assertRedirect(route('login'));
        $this->get(route('login'))->assertOk();
        $this->get(route('register'))->assertOk();
    }

    public function test_register_logs_in_and_first_account_adopts_ownerless_outfits(): void
    {
        $old = Outfit::create(['title' => 'Outfit cũ', 'items' => '- a']);

        $this->post(route('register'), [
            'name' => 'Lan', 'email' => 'lan@example.com',
            'password' => 'password123', 'password_confirmation' => 'password123',
        ])->assertRedirect(route('outfits.index'));

        $lan = User::where('email', 'lan@example.com')->first();
        $this->assertAuthenticatedAs($lan);
        $this->assertSame($lan->id, $old->fresh()->user_id);

        // Tài khoản thứ hai không nhận outfit của ai
        $this->post(route('logout'));
        $this->post(route('register'), [
            'name' => 'Mai', 'email' => 'mai@example.com',
            'password' => 'password123', 'password_confirmation' => 'password123',
        ]);
        $this->assertSame(0, User::where('email', 'mai@example.com')->first()->outfits()->count());
    }

    public function test_register_validates(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->post(route('register'), [
            'name' => '', 'email' => 'taken@example.com', 'password' => 'short', 'password_confirmation' => 'x',
        ])->assertSessionHasErrors(['name', 'email', 'password']);
        $this->assertGuest();
    }

    public function test_login_and_logout(): void
    {
        $user = User::factory()->create(['password' => 'password123']);

        $this->post(route('login'), ['email' => $user->email, 'password' => 'wrong'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->post(route('login'), ['email' => $user->email, 'password' => 'password123'])
            ->assertRedirect(route('outfits.index'));
        $this->assertAuthenticatedAs($user);

        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_each_user_sees_and_manages_only_own_outfits(): void
    {
        $me = User::factory()->create();
        $other = User::factory()->create();
        $mine = $me->outfits()->create(['title' => 'Của tôi', 'items' => '- a']);
        $theirs = $other->outfits()->create(['title' => 'Của người khác', 'items' => '- b', 'is_public' => false]);
        $theirs->syncHashtags('hashtagcuanguoikhac');

        $this->actingAs($me);
        $this->get('/')->assertSee('Của tôi')->assertDontSee('Của người khác')->assertDontSee('hashtagcuanguoikhac');
        $this->get(route('outfits.show', $theirs))->assertNotFound();
        $this->get(route('outfits.edit', $theirs))->assertNotFound();
        $this->put(route('outfits.update', $theirs), ['title' => 'x', 'items' => 'x', 'hashtags' => 'x'])->assertNotFound();
        $this->delete(route('outfits.destroy', $theirs))->assertNotFound();
        $this->assertNotNull($theirs->fresh());

        $this->post(route('outfits.store'), ['title' => 'Mới', 'items' => '- c', 'hashtags' => 'he'])->assertRedirect();
        $this->assertSame(2, $me->outfits()->count());
        $this->get(route('outfits.show', $mine))->assertOk();
    }

    public function test_settings_page_only_has_api_token(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->get(route('settings.edit'))->assertOk()->assertSee('API token')
            ->assertDontSee('Câu sửa nhanh')->assertDontSee('fix_prompt', false)->assertDontSee('common_prompt', false);
        $this->assertContains($this->put('/settings/prompts', ['fix_prompt' => 'x'])->status(), [404, 405]);
    }

    public function test_issue_token_shows_it_once(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('settings.token'))->assertRedirect();
        $token = session('api_token');

        $this->assertNotEmpty($token);
        $this->assertSame(hash('sha256', $token), $user->fresh()->api_token);
    }

    public function test_header_shows_account_menu(): void
    {
        $user = User::factory()->create(['name' => 'Lan', 'email' => 'lan@example.com']);
        $user->outfits()->create(['title' => 'A', 'items' => '- Áo']);
        $user->outfits()->create(['title' => 'B', 'items' => '- Váy']);

        $this->actingAs($user)->get('/')->assertOk()
            ->assertSeeInOrder(['account__toggle', 'Lan', 'lan@example.com', '2 outfit', 'Mẫu ảnh outfit', 'Cài đặt', 'Đăng xuất'], false);
    }
}
