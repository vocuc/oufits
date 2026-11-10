<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CoverImageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['filesystems.cover_disk' => 'covers-test']);
        Storage::fake('covers-test');
    }

    private function payload(array $extra = []): array
    {
        return ['title' => 'Len đen', 'items' => '- Áo len đen', 'hashtags' => 'thudong', ...$extra];
    }

    public function test_upload_cover_when_creating_and_show_it_on_list(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('outfits.store'), $this->payload([
            'cover' => UploadedFile::fake()->image('anh.jpg', 800, 1000),
        ]))->assertRedirect();

        $outfit = $user->outfits()->first();
        $this->assertStringStartsWith("ootd/{$user->id}/", $outfit->cover_path);
        Storage::disk('covers-test')->assertExists($outfit->cover_path);

        $this->get('/')->assertSee($outfit->coverUrl(), false);
        $this->get($outfit->coverUrl())->assertRedirect();
    }

    public function test_replace_and_remove_cover_deletes_old_files(): void
    {
        $user = User::factory()->create();
        $outfit = $user->outfits()->create(['title' => 'Len đen', 'items' => '- a']);
        $outfit->replaceCover(UploadedFile::fake()->image('1.jpg'));
        $first = $outfit->cover_path;

        $this->actingAs($user)->put(route('outfits.update', $outfit), $this->payload([
            'cover' => UploadedFile::fake()->image('2.png'),
        ]));
        $second = $outfit->fresh()->cover_path;
        $this->assertNotSame($first, $second);
        Storage::disk('covers-test')->assertMissing($first);

        $this->put(route('outfits.update', $outfit), $this->payload(['remove_cover' => '1']));
        $this->assertNull($outfit->fresh()->cover_path);
        Storage::disk('covers-test')->assertMissing($second);
    }

    public function test_deleting_outfit_deletes_cover_file(): void
    {
        $user = User::factory()->create();
        $outfit = $user->outfits()->create(['title' => 'Len đen', 'items' => '- a']);
        $outfit->replaceCover(UploadedFile::fake()->image('1.jpg'));
        $path = $outfit->cover_path;

        $this->actingAs($user)->delete(route('outfits.destroy', $outfit));
        Storage::disk('covers-test')->assertMissing($path);
    }

    public function test_rejects_non_image(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('outfits.store'), $this->payload([
                'cover' => UploadedFile::fake()->create('a.pdf', 10, 'application/pdf'),
            ]))->assertSessionHasErrors('cover');
    }

    public function test_other_users_cannot_view_cover(): void
    {
        $outfit = User::factory()->create()->outfits()->create(['title' => 'x', 'items' => '- a']);
        $outfit->replaceCover(UploadedFile::fake()->image('1.jpg'));

        $this->actingAs(User::factory()->create())->get($outfit->coverUrl())->assertNotFound();
    }

    public function test_api_can_attach_cover_to_existing_outfit(): void
    {
        $user = User::factory()->create();
        $token = $user->issueApiToken();
        $outfit = $user->outfits()->create(['title' => 'x', 'items' => '- a']);
        $theirs = User::factory()->create()->outfits()->create(['title' => 'y', 'items' => '- b']);

        $this->withToken($token)->post("/api/outfits/{$outfit->id}/cover", [
            'cover' => UploadedFile::fake()->image('c.webp'),
        ], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('data.has_cover', true);

        $this->withToken($token)->post("/api/outfits/{$theirs->id}/cover", [
            'cover' => UploadedFile::fake()->image('c.jpg'),
        ], ['Accept' => 'application/json'])->assertNotFound();
    }

    public function test_api_can_create_outfit_with_cover(): void
    {
        $token = User::factory()->create()->issueApiToken();

        $this->withToken($token)->post('/api/outfits', $this->payload([
            'cover' => UploadedFile::fake()->image('c.jpg'),
        ]), ['Accept' => 'application/json'])->assertCreated()->assertJsonPath('data.has_cover', true);
    }

    public function test_cover_is_resized_to_600px_webp(): void
    {
        $user = User::factory()->create();
        $outfit = $user->outfits()->create(['title' => 'A', 'items' => '- Áo']);

        $outfit->replaceCover(UploadedFile::fake()->image('big.jpg', 2000, 1500));

        $this->assertStringEndsWith('.webp', $outfit->cover_path);
        [$w, $h, $type] = getimagesizefromstring(Storage::disk('covers-test')->get($outfit->cover_path));
        $this->assertSame([600, 450, IMAGETYPE_WEBP], [$w, $h, $type]);

        $outfit->replaceCover(UploadedFile::fake()->image('small.png', 300, 400));
        [$w, $h] = getimagesizefromstring(Storage::disk('covers-test')->get($outfit->cover_path));
        $this->assertSame([300, 400], [$w, $h]); // ảnh nhỏ không bị phóng to
    }
}
