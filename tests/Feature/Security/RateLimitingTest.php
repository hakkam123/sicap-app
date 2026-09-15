<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RateLimitingTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_token_creation_rate_limiting(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['*']);

        for ($i = 0; $i < 5; $i++) {
            $response = $this->postJson('/api/tokens/create', [
                'token_name' => 'test-token-' . $i,
            ]);
            $response->assertStatus(200);
        }

        // 6th request should hit rate limiter (limit is 5 per minute)
        $rateLimitedResponse = $this->postJson('/api/tokens/create', [
            'token_name' => 'test-token-exceeded',
        ]);

        $rateLimitedResponse->assertStatus(429);
    }

    public function test_login_rate_limiting_after_multiple_failed_attempts(): void
    {
        $user = User::factory()->create(['password' => bcrypt('correct_password')]);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', [
                'email' => $user->email,
                'password' => 'wrong_password',
            ]);
        }

        // 6th attempt should be blocked by login rate limiting
        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong_password',
        ]);

        $response->assertSessionHasErrors('email');
    }
}

