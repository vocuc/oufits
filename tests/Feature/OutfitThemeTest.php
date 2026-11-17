<?php

namespace Tests\Feature;

use App\Models\Outfit;
use App\Models\OutfitTheme;
use App\Models\OutfitThemeHook;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Trang "Chủ đề" (/themes) + /api/themes: chủ đề chỉ gom nhóm, mỗi hook có hashtag riêng (tìm outfit) + duyệt riêng.
 */
class OutfitThemeTest extends TestCase
{
    use RefreshDatabase;

    private function outfit(User $user, string $tags, bool $cover = true): Outfit
    {
        $o = $user->outfits()->create(['title' => 'Bộ '.$tags, 'items' => 'x', 'is_public' => false]);
        $o->syncHashtags($tags);
        if ($cover) {
            $o->forceFill(['cover_path' => 'covers/'.$o->id.'.jpg'])->save();
        }

        return $o;
    }

    public function test_owner_manages_themes_and_hooks_on_web(): void
    {
        $user = User::factory()->create();
        $this->outfit($user, 'chanvaymini tatcaoco thudong');
        $this->actingAs($user)->get('/themes')->assertOk()->assertSee('Chưa có chủ đề nào');

        $this->actingAs($user)->post('/themes', ['name' => ' Váy ngắn ', 'note' => 'mùa đông'])->assertRedirect();
        $theme = OutfitTheme::query()->sole();
        $this->assertSame('Váy ngắn', $theme->name);
        $this->actingAs($user)->post('/themes', ['name' => 'Váy ngắn'])->assertSessionHasErrors('name');

        $this->actingAs($user)->post("/themes/{$theme->id}/hooks", ['body' => '  Váy ngắn mix  *tất cao cổ* ', 'tags' => '#chanvaymini, TatCaoCo thudong'])
            ->assertRedirect();
        $hook = OutfitThemeHook::query()->sole();
        $this->assertSame('Váy ngắn mix *tất cao cổ*', $hook->body);
        $this->assertSame(['chanvaymini', 'tatcaoco', 'thudong'], $hook->tagList());
        $this->assertSame(OutfitThemeHook::PENDING, $hook->fresh()->status);
        $this->assertSame(1, $hook->outfitCount());
        $this->actingAs($user)->post("/themes/{$theme->id}/hooks", ['body' => 'Váy ngắn mix *tất cao cổ*', 'tags' => 'x'])
            ->assertSessionHasErrors('body');
        $this->actingAs($user)->post("/themes/{$theme->id}/hooks", ['body' => 'Rỗng', 'tags' => '# ,'])->assertSessionHasErrors('tags');

        // duyệt từng hook; "Lưu" (status rỗng) sửa câu / hashtag, giữ trạng thái
        $this->actingAs($user)->put("/theme-hooks/{$hook->id}", ['body' => $hook->body, 'tags' => 'chanvaymini', 'status' => 'approved'])->assertRedirect();
        $this->assertSame(OutfitThemeHook::APPROVED, $hook->fresh()->status);
        $this->assertNotNull($hook->fresh()->reviewed_at);
        $this->actingAs($user)->put("/theme-hooks/{$hook->id}", ['body' => 'Hook mới', 'tags' => 'chanvaymini tatcaoco', 'status' => '']);
        $this->assertSame(['Hook mới', OutfitThemeHook::APPROVED], [$hook->fresh()->body, $hook->fresh()->status]);
        $this->actingAs($user)->get('/themes')->assertSee('Hook mới')->assertSee('1 outfit');
        $this->actingAs($user)->get('/themes?status=pending')->assertDontSee('Hook mới');

        $other = User::factory()->create();
        $this->actingAs($other)->put("/theme-hooks/{$hook->id}", ['body' => 'x', 'tags' => 'x'])->assertNotFound();
        $this->actingAs($other)->delete("/theme-hooks/{$hook->id}")->assertNotFound();
        $this->actingAs($other)->post("/themes/{$theme->id}/hooks", ['body' => 'x', 'tags' => 'x'])->assertNotFound();
        $this->actingAs($other)->delete("/themes/{$theme->id}")->assertNotFound();
        $this->actingAs($other)->get('/themes')->assertDontSee('Hook mới');

        $this->actingAs($user)->delete("/theme-hooks/{$hook->id}")->assertRedirect();
        $this->assertSame(0, OutfitThemeHook::query()->count());
        $theme->hooks()->create(['body' => 'A', 'tags' => 'a']);
        $this->actingAs($user)->delete("/themes/{$theme->id}")->assertRedirect();
        $this->assertSame([0, 0], [OutfitTheme::query()->count(), OutfitThemeHook::query()->count()]);
    }

    public function test_api_upserts_hooks_without_changing_status_and_filters_by_status(): void
    {
        $user = User::factory()->create();
        $token = $user->issueApiToken();
        $this->outfit($user, 'chanvaymini tatcaoco thudong');
        $this->outfit($user, 'chanvaymini tatcaoco');
        $this->outfit($user, 'chanvaymini tatcaoco', cover: false);       // không ảnh gốc -> không đếm
        $this->outfit(User::factory()->create(), 'chanvaymini tatcaoco'); // của người khác

        $hooks = [['body' => '*Trễ vai* dễ mặc', 'tags' => ['chanvaymini', '#TatCaoCo']], ['body' => 'Đông này', 'tags' => 'chanvaymini thudong']];
        $this->withToken($token)->postJson('/api/themes', ['name' => 'Váy ngắn', 'hooks' => $hooks, 'status' => 'approved'])
            ->assertCreated()->assertJsonPath('created', true)
            ->assertJsonPath('data.hooks.0.body', '*Trễ vai* dễ mặc')->assertJsonPath('data.hooks.0.tags', ['chanvaymini', 'tatcaoco'])
            ->assertJsonPath('data.hooks.0.status', 'pending')->assertJsonPath('data.hooks.0.outfit_count', 2)
            ->assertJsonPath('data.hooks.1.outfit_count', 1);
        $this->withToken($token)->postJson('/api/themes', ['name' => 'X', 'hooks' => [['body' => 'A', 'tags' => '']]])->assertUnprocessable();
        $this->withToken($token)->postJson('/api/themes', ['name' => 'X', 'hooks' => [['body' => 'A']]])->assertUnprocessable();

        $theme = $user->outfitThemes()->sole();
        $theme->hooks()->where('body', 'Đông này')->update(['status' => OutfitThemeHook::APPROVED]);
        // upsert theo name + body: sửa tags hook cũ (giữ đã duyệt), thêm hook mới chờ duyệt, không xoá hook không gửi
        $this->withToken($token)->postJson('/api/themes', ['name' => 'Váy ngắn', 'note' => 'n',
            'hooks' => [['body' => 'Đông này', 'tags' => 'chanvaymini'], ['body' => 'Hook 3', 'tags' => 'a']]])
            ->assertOk()->assertJsonPath('created', false)->assertJsonCount(3, 'data.hooks')
            ->assertJsonPath('data.hooks.1.status', 'approved')->assertJsonPath('data.hooks.1.tags', ['chanvaymini'])
            ->assertJsonPath('data.hooks.2.status', 'pending')->assertJsonPath('data.note', 'n');

        $hookId = $theme->hooks()->where('body', 'Hook 3')->value('id');
        $this->withToken($token)->putJson("/api/theme-hooks/{$hookId}", ['tags' => 'chanvaymini tatcaoco thudong'])
            ->assertOk()->assertJsonPath('data.outfit_count', 1)->assertJsonPath('data.status', 'pending');
        $this->withToken($token)->putJson("/api/theme-hooks/{$hookId}", ['tags' => ''])->assertUnprocessable();

        $this->withToken($token)->postJson('/api/themes', ['name' => 'Chưa duyệt', 'hooks' => [['body' => 'B', 'tags' => 'b']]])->assertCreated();
        $this->withToken($token)->getJson('/api/themes')->assertOk()->assertJsonCount(2, 'data');
        $this->withToken($token)->getJson('/api/themes?status=approved')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Váy ngắn')->assertJsonCount(1, 'data.0.hooks')
            ->assertJsonPath('data.0.hooks.0.body', 'Đông này')->assertJsonMissingPath('data.0.user_id');
        $this->withToken($token)->getJson('/api/themes?status=abc')->assertUnprocessable();

        $otherToken = User::factory()->create()->issueApiToken();
        $this->withToken($otherToken)->getJson('/api/themes')->assertOk()->assertJsonCount(0, 'data');
        $this->withToken($otherToken)->putJson("/api/theme-hooks/{$hookId}", ['tags' => 'x'])->assertNotFound();
        $this->flushHeaders()->getJson('/api/themes')->assertUnauthorized();
    }

    /** Migration tách hook: mỗi dòng của outfit_themes.hooks cũ -> 1 hook (tags + trạng thái của chủ đề), "ễ" không hỏng. */
    public function test_migration_moves_old_theme_hooks_into_rows(): void
    {
        $this->artisan('migrate:rollback', ['--step' => 1])->assertSuccessful();
        $user = User::factory()->create();
        DB::table('outfit_themes')->insert([
            'user_id' => $user->id, 'name' => 'Áo trễ vai', 'tags' => 'aotrevai', 'status' => 'approved',
            'hooks' => "*Trễ vai* nhẹ thôi\nMùa đông dễ mặc\n\n*Trễ vai* nhẹ thôi", 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->artisan('migrate')->assertSuccessful();

        $theme = OutfitTheme::query()->sole();
        $this->assertSame(['*Trễ vai* nhẹ thôi', 'Mùa đông dễ mặc'], $theme->hooks->pluck('body')->all());
        $this->assertSame([['aotrevai'], 'approved'], [$theme->hooks[0]->tagList(), $theme->hooks[0]->status]);
        $token = $user->issueApiToken();
        $this->withToken($token)->getJson('/api/themes?status=approved')->assertOk()->assertJsonCount(2, 'data.0.hooks');
    }
}
