<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OutfitListPaginationTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_shows_30_newest_then_loads_20_more(): void
    {
        $user = User::factory()->create();
        foreach (range(1, 55) as $i) {
            $user->outfits()->create(['title' => "Outfit số {$i}.", 'items' => '- Áo']);
        }

        $this->actingAs($user)->get('/')->assertOk()
            ->assertSee('55 outfit')
            ->assertSee('Outfit số 55.')->assertSee('Outfit số 26.')->assertDontSee('Outfit số 25.')
            ->assertSee('data-next="30"', false);

        $more = $this->getJson('/?offset=30')->assertOk()->assertJsonPath('next_offset', 50)->json('html');
        $this->assertStringContainsString('Outfit số 25.', $more);
        $this->assertStringContainsString('Outfit số 6.', $more);
        $this->assertStringNotContainsString('Outfit số 5.', $more);

        $last = $this->getJson('/?offset=50')->assertOk()->assertJsonPath('next_offset', null);
        $this->assertSame(5, substr_count($last->json('html'), '<article class="card">'));
    }
}
