<?php

namespace Tests\Feature;

use App\Models\Guideline;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Trang "Quy tắc" (/guidelines) + GET /api/guidelines (chỉ quy tắc đang bật). */
class GuidelineTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_adds_edits_toggles_and_deletes_rules(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/guidelines')->assertOk()
            ->assertSee('Tạo outfit')->assertSee('Ảnh bìa')->assertSee('Đặt hook');

        $this->actingAs($user)->post('/guidelines', ['category' => 'hook', 'body' => '  Hook tối đa 8 từ  '])->assertRedirect();
        $this->actingAs($user)->post('/guidelines', ['category' => 'hook', 'body' => 'Không dùng tiếng Anh']);
        $this->actingAs($user)->post('/guidelines', ['category' => 'xyz', 'body' => 'x'])->assertSessionHasErrors('category');
        [$a, $b] = Guideline::query()->orderBy('id')->get()->all();
        $this->assertSame('Hook tối đa 8 từ', $a->body);
        $this->assertSame([1, 2], [$a->sort, $b->sort]);

        $this->actingAs($user)->put("/guidelines/{$a->id}", ['body' => 'Hook tối đa 6 từ', 'is_active' => '1']);
        $this->actingAs($user)->put("/guidelines/{$b->id}", ['body' => 'Không dùng tiếng Anh']); // bỏ tick -> tắt
        $this->assertSame('Hook tối đa 6 từ', $a->fresh()->body);
        $this->assertFalse($b->fresh()->is_active);
        $this->actingAs($user)->get('/guidelines')->assertSee('Hook tối đa 6 từ');

        $other = User::factory()->create();
        $this->actingAs($other)->put("/guidelines/{$a->id}", ['body' => 'hack'])->assertNotFound();
        $this->actingAs($other)->delete("/guidelines/{$a->id}")->assertNotFound();
        $this->actingAs($other)->get('/guidelines')->assertDontSee('Hook tối đa 6 từ');

        $this->actingAs($user)->delete("/guidelines/{$b->id}")->assertRedirect();
        $this->assertSame(1, Guideline::query()->count());
    }

    public function test_api_lists_only_active_rules_of_token_owner(): void
    {
        $user = User::factory()->create();
        $token = $user->issueApiToken();
        $user->guidelines()->create(['category' => 'outfit', 'body' => 'Ưu tiên chân váy ngắn', 'sort' => 1]);
        $user->guidelines()->create(['category' => 'cover', 'body' => 'Logo không che giày', 'sort' => 1]);
        $user->guidelines()->create(['category' => 'hook', 'body' => 'Đã tắt', 'sort' => 1, 'is_active' => false]);
        User::factory()->create()->guidelines()->create(['category' => 'hook', 'body' => 'Của người khác']);

        $this->withToken($token)->getJson('/api/guidelines')->assertOk()->assertJsonCount(2, 'data')
            ->assertJsonMissing(['body' => 'Đã tắt'])->assertJsonMissing(['body' => 'Của người khác']);
        $this->withToken($token)->getJson('/api/guidelines?category=cover')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.body', 'Logo không che giày');
        $this->withToken($token)->getJson('/api/guidelines?category=abc')->assertUnprocessable();

        $this->withToken($token)->postJson('/api/guidelines', ['category' => 'cover', 'body' => ' Khổ 3:4 '])
            ->assertCreated()->assertJsonPath('data.sort', 2)->assertJsonPath('created', true);
        $this->withToken($token)->postJson('/api/guidelines', ['category' => 'cover', 'body' => 'Khổ 3:4'])
            ->assertOk()->assertJsonPath('created', false);
        $this->withToken($token)->postJson('/api/guidelines', ['category' => 'abc', 'body' => 'x'])->assertUnprocessable();
        $this->assertSame(2, $user->guidelines()->where('category', 'cover')->count());
    }
}
