<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_json_responses_have_correct_json_content_type(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/dashboard/drill-down?type=unassigned&id=all');

        $response->assertStatus(200);
        $this->assertStringContainsString('application/json', $response->headers->get('content-type'));
    }

    public function test_unauthenticated_api_response_does_not_leak_stack_traces(): void
    {
        $response = $this->postJson('/api/consumes/sync', []);

        $response->assertStatus(401);
        $this->assertStringNotContainsString('SQLSTATE', $response->getContent());
        $this->assertStringNotContainsString('Stack trace:', $response->getContent());
    }

    public function test_admin_routes_do_not_expose_sensitive_system_internals_on_404(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/non-existent-secret-url');

        $response->assertStatus(404);
        $this->assertStringNotContainsString('DB_PASSWORD', $response->getContent());
    }
}

