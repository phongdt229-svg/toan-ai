<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Services\Auth\AccountDeletionService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AvatarTest extends TestCase
{
    use RefreshDatabase;

    private function student(): User
    {
        $this->seed(RolePermissionSeeder::class);
        Storage::fake('public');
        $user = User::factory()->create();
        $user->assignRole('student');

        return $user;
    }

    public function test_upload_is_redrawn_as_square_jpeg_and_replaces_the_old_one(): void
    {
        $user = $this->student();

        $this->actingAs($user)->post(route('account.avatar.store'), ['avatar' => UploadedFile::fake()->image('a.png', 600, 300)])
            ->assertSessionHasNoErrors();

        $first = $user->refresh()->avatar;
        $this->assertStringStartsWith('avatars/', $first);
        Storage::disk('public')->assertExists($first);

        [$w, $h, $type] = getimagesizefromstring(Storage::disk('public')->get($first));
        $this->assertSame([256, 256, IMAGETYPE_JPEG], [$w, $h, $type]);

        $this->post(route('account.avatar.store'), ['avatar' => UploadedFile::fake()->image('b.jpg', 400, 400)]);
        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($user->refresh()->avatar);
    }

    public function test_non_image_and_disguised_files_are_rejected(): void
    {
        $user = $this->student();

        $this->actingAs($user)->post(route('account.avatar.store'), ['avatar' => UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')])
            ->assertSessionHasErrors('avatar');

        // Đuôi .png nhưng ruột là PHP.
        $fake = UploadedFile::fake()->createWithContent('shell.png', '<?php echo 1;');
        $this->post(route('account.avatar.store'), ['avatar' => $fake])->assertSessionHasErrors('avatar');

        $this->assertNull($user->refresh()->avatar);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_too_large_file_is_rejected(): void
    {
        $user = $this->student();

        $this->actingAs($user)->post(route('account.avatar.store'), ['avatar' => UploadedFile::fake()->image('big.jpg', 500, 500)->size(3000)])
            ->assertSessionHasErrors('avatar');
    }

    public function test_remove_deletes_the_file(): void
    {
        $user = $this->student();
        $this->actingAs($user)->post(route('account.avatar.store'), ['avatar' => UploadedFile::fake()->image('a.jpg', 300, 300)]);
        $path = $user->refresh()->avatar;

        $this->delete(route('account.avatar.destroy'))->assertRedirect();

        $this->assertNull($user->refresh()->avatar);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_purging_an_account_removes_the_avatar_file(): void
    {
        $user = $this->student();
        $this->actingAs($user)->post(route('account.avatar.store'), ['avatar' => UploadedFile::fake()->image('a.jpg', 300, 300)]);
        $path = $user->refresh()->avatar;

        app(AccountDeletionService::class)->request($user);
        $this->travel(AccountDeletionService::GRACE_DAYS + 1)->days();
        app(AccountDeletionService::class)->purgeDue();

        Storage::disk('public')->assertMissing($path);
    }

    public function test_guest_cannot_upload(): void
    {
        $this->post(route('account.avatar.store'), [])->assertRedirect(route('login'));
    }
}
