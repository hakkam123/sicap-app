<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
    }

    public function test_admin_can_view_users_list_with_inertia(): void
    {
        User::factory()->count(3)->create();

        $response = $this->actingAs($this->admin)->get(route('users.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('User/Index')
            ->has('users.data')
            ->has('filters')
        );
    }

    public function test_admin_can_create_new_user(): void
    {
        $response = $this->actingAs($this->admin)->post(route('users.store'), [
            'name' => 'Charlie Chaplin',
            'email' => 'charlie@visteon.com',
            'password' => 'Secret123!',
            'password_confirmation' => 'Secret123!',
            'role' => 'user',
        ]);

        $response->assertRedirect(route('users.index'));
        $response->assertSessionHas('success');

        $user = User::where('email', 'charlie@visteon.com')->first();
        $this->assertNotNull($user);
        $this->assertEquals('Charlie Chaplin', $user->name);
        $this->assertEquals('user', $user->role);
        $this->assertTrue(Hash::check('Secret123!', $user->password));
    }

    public function test_admin_can_update_user_without_changing_password(): void
    {
        $user = User::factory()->create([
            'name' => 'Old User',
            'email' => 'old@visteon.com',
            'password' => Hash::make('original_password'),
            'role' => 'user',
        ]);

        $response = $this->actingAs($this->admin)->put(route('users.update', $user), [
            'name' => 'Updated User',
            'email' => 'updated@visteon.com',
            'role' => 'admin',
        ]);

        $response->assertRedirect(route('users.index'));
        $response->assertSessionHas('success');

        $user->refresh();
        $this->assertEquals('Updated User', $user->name);
        $this->assertEquals('updated@visteon.com', $user->email);
        $this->assertEquals('admin', $user->role);
        $this->assertTrue(Hash::check('original_password', $user->password));
    }

    public function test_admin_cannot_delete_their_own_account(): void
    {
        $response = $this->actingAs($this->admin)
            ->from(route('users.index'))
            ->delete(route('users.destroy', $this->admin));

        $response->assertRedirect(route('users.index'));
        $response->assertSessionHas('error', 'Tidak dapat menghapus akun Anda sendiri');

        $this->assertDatabaseHas('users', ['id' => $this->admin->id, 'deleted_at' => null]);
    }

    public function test_admin_can_delete_other_user_account(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($this->admin)->delete(route('users.destroy', $user));

        $response->assertRedirect(route('users.index'));
        $response->assertSessionHas('success');

        $this->assertSoftDeleted('users', ['id' => $user->id]);
    }
}

