<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login_on_protected_routes(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/consume')->assertRedirect('/login');
        $this->get('/reports')->assertRedirect('/login');
        $this->get('/users')->assertRedirect('/login');
    }

    public function test_regular_user_can_access_general_features(): void
    {
        $user = User::factory()->user()->create();

        $this->actingAs($user)->get('/dashboard')->assertStatus(200);
        $this->actingAs($user)->get('/consume')->assertStatus(200);
        $this->actingAs($user)->get('/reports')->assertStatus(200);
        $this->actingAs($user)->get('/profile')->assertStatus(200);
    }

    public function test_regular_user_is_forbidden_from_admin_only_routes(): void
    {
        $user = User::factory()->user()->create();

        $this->actingAs($user)->get('/users')->assertStatus(403);
        $this->actingAs($user)->get('/areas')->assertStatus(403);
        $this->actingAs($user)->get('/machines')->assertStatus(403);
        $this->actingAs($user)->get('/part-numbers')->assertStatus(403);
        $this->actingAs($user)->get('/mapping')->assertStatus(403);
        $this->actingAs($user)->get('/error-monitoring')->assertStatus(403);
        $this->actingAs($user)->get('/import-logs')->assertStatus(403);
    }

    public function test_admin_user_can_access_admin_routes(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/users')->assertStatus(200);
        $this->actingAs($admin)->get('/areas')->assertStatus(200);
        $this->actingAs($admin)->get('/machines')->assertStatus(200);
        $this->actingAs($admin)->get('/part-numbers')->assertStatus(200);
        $this->actingAs($admin)->get('/mapping')->assertStatus(200);
        $this->actingAs($admin)->get('/error-monitoring')->assertStatus(200);
        $this->actingAs($admin)->get('/import-logs')->assertStatus(200);
    }

    public function test_root_route_redirects_correctly(): void
    {
        $this->get('/')->assertRedirect('/login');

        $user = User::factory()->create();
        $this->actingAs($user)->get('/')->assertRedirect(route('dashboard'));
    }
}

