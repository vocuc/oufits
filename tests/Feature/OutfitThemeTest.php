<?php

namespace Tests\Feature;

use App\Models\Outfit;
use App\Models\OutfitTheme;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Trang "Chủ đề" (/themes) + GET/POST /api/themes (worker chọn chủ đề đã duyệt, đếm outfit khớp). */
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

    public function test_owner_adds_edits_reviews_and_deletes_themes(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/themes')->assertOk()->assertSee('Chưa có chủ đề nào');

        $this->actingAs($user)->post('/themes', [
            'name' => ' Váy ngắn + tất ', 'tags' => '#chanvaymini, TatCaoCo chanvaymini',
            'hooks' => "Váy ngắn mix *tất cao cổ*\n\n  Váy ngắn mix *tất cao cổ*  \nĐông này mặc *váy ngắn*", 'season' => 'thudong',
        ])->assertRedirect();
        $theme = OutfitTheme::query()->sole();
        $this->assertSame('Váy ngắn + tất', $theme->name);
        $this->assertSame(['chanvaymini', 'tatcaoco'], $theme->tagList());
        $this->assertSame(['Váy ngắn mix *tất cao cổ*', 'Đông này mặc *váy ngắn*'], $theme->hookList());
        $this->assertSame(OutfitTheme::PENDING, $theme->fresh()->status);

        $this->actingAs($user)->post('/themes', ['name' => 'Váy ngắn + tất', 'tags' => 'a', 'hooks' => 'b'])
            ->assertSessionHasErrors('name');
        $this->actingAs($user)->post('/themes', ['name' => 'Rỗng', 'tags' => '# ,', 'hooks' => 'b'])
            ->assertSessionHasErrors('tags');

        $this->actingAs($user)->put("/themes/{$theme->id}", [
            'name' => 'Váy ngắn + tất', 'tags' => 'chanvaymini tatcaoco', 'hooks' => 'Hook mới', 'season' => '', 'status' => 'approved',
        ])->assertRedirect();
        $theme->refresh();
        $this->assertSame(OutfitTheme::APPROVED, $theme->status);
        $this->assertNull($theme->season);
        $this->assertNotNull($theme->reviewed_at);
        // "Lưu" (status rỗng) không đổi trạng thái
        $this->actingAs($user)->put("/themes/{$theme->id}", ['name' => 'Váy ngắn + tất', 'tags' => 'chanvaymini', 'hooks' => 'Hook mới', 'status' => '']);
        $this->assertSame(OutfitTheme::APPROVED, $theme->fresh()->status);
        $this->actingAs($user)->get('/themes')->assertSee('Hook mới')->assertSee('0 outfit khớp');

        $other = User::factory()->create();
        $this->actingAs($other)->put("/themes/{$theme->id}", ['name' => 'x', 'tags' => 'x', 'hooks' => 'x'])->assertNotFound();
        $this->actingAs($other)->delete("/themes/{$theme->id}")->assertNotFound();
        $this->actingAs($other)->get('/themes')->assertDontSee('Hook mới');

        $this->actingAs($user)->delete("/themes/{$theme->id}")->assertRedirect();
        $this->assertSame(0, OutfitTheme::query()->count());
    }

    public function test_api_lists_counts_and_upserts_without_changing_status(): void
    {
        $user = User::factory()->create();
        $token = $user->issueApiToken();
        $this->outfit($user, 'chanvaymini tatcaoco thudong');
        $this->outfit($user, 'chanvaymini tatcaoco');
        $this->outfit($user, 'chanvaymini tatcaoco', cover: false);   // không ảnh gốc -> không đếm
        $this->outfit($user, 'chanvaymini');                          // thiếu tag -> không đếm
        $this->outfit(User::factory()->create(), 'chanvaymini tatcaoco'); // của người khác

        $this->withToken($token)->postJson('/api/themes', [
            'name' => 'Váy ngắn + tất', 'tags' => ['chanvaymini', '#TatCaoCo'], 'hooks' => ['Hook 1', 'Hook 2', ''],
            'season' => 'thudong', 'status' => 'approved',
        ])->assertCreated()->assertJsonPath('created', true)->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.tags', ['chanvaymini', 'tatcaoco'])->assertJsonPath('data.hooks', ['Hook 1', 'Hook 2'])
            ->assertJsonPath('data.outfit_count', 2);
        $this->withToken($token)->postJson('/api/themes', ['name' => 'Short trắng', 'tags' => 'quanshort mautrang', 'hooks' => "A\nB"])
            ->assertCreated();
        $this->withToken($token)->postJson('/api/themes', ['name' => 'X', 'tags' => '', 'hooks' => 'A'])->assertUnprocessable();
        $this->withToken($token)->postJson('/api/themes', ['name' => 'X', 'tags' => 'a', 'hooks' => 'A', 'season' => 'he'])->assertUnprocessable();

        $theme = $user->outfitThemes()->where('name', 'Váy ngắn + tất')->first();
        $theme->update(['status' => OutfitTheme::APPROVED]);
        // upsert theo name: sửa hook, giữ trạng thái đã duyệt
        $this->withToken($token)->postJson('/api/themes', ['name' => 'Váy ngắn + tất', 'tags' => 'chanvaymini tatcaoco', 'hooks' => 'Hook 3'])
            ->assertOk()->assertJsonPath('created', false)->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.hooks', ['Hook 3']);

        $this->withToken($token)->getJson('/api/themes')->assertOk()->assertJsonCount(2, 'data');
        $this->withToken($token)->getJson('/api/themes?status=approved')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Váy ngắn + tất')->assertJsonPath('data.0.outfit_count', 2)
            ->assertJsonMissingPath('data.0.user_id');
        $this->withToken($token)->getJson('/api/themes?status=abc')->assertUnprocessable();
        $this->withToken(User::factory()->create()->issueApiToken())->getJson('/api/themes')->assertOk()->assertJsonCount(0, 'data');
        $this->flushHeaders()->getJson('/api/themes')->assertUnauthorized();
    }

    /** "ễ" = E1 BB 85: preg_split('/\R/') không có /u cắt ở byte 0x85 -> JSON lỗi "Malformed UTF-8" (prod 2026-11-17). */
    public function test_vietnamese_hooks_with_byte_0x85_stay_intact(): void
    {
        $user = User::factory()->create();
        $token = $user->issueApiToken();
        $hooks = ['*Trễ vai* nhẹ thôi là đủ hút mắt', 'Mùa đông dễ mặc nhất'];
        $this->withToken($token)->postJson('/api/themes', ['name' => 'Áo trễ vai', 'tags' => 'aotrevai', 'hooks' => $hooks])
            ->assertCreated()->assertJsonPath('data.hooks', $hooks);
        $this->withToken($token)->postJson('/api/themes', ['name' => 'Áo len', 'tags' => 'aolen', 'hooks' => implode("\n", $hooks)])
            ->assertCreated()->assertJsonPath('data.hooks', $hooks);
        $this->withToken($token)->getJson('/api/themes')->assertOk()->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.hooks', $hooks);
        $this->actingAs($user)->post('/themes', ['name' => 'Web ễ', 'tags' => 'Trễ vai', 'hooks' => "Dễ mặc\nTrễ vai"])->assertRedirect();
        $this->assertSame(['Dễ mặc', 'Trễ vai'], $user->outfitThemes()->where('name', 'Web ễ')->sole()->hookList());
        $this->actingAs($user)->get('/themes')->assertOk()->assertSee('Dễ mặc');
    }
}
