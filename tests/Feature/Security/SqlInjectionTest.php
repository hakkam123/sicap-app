<?php

namespace Tests\Feature\Security;

use App\Models\Area;
use App\Models\Consume;
use App\Models\PartNumber;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SqlInjectionTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
    }

    public function test_sql_injection_payload_in_search_query_is_safely_escaped(): void
    {
        $payload = "' OR 1=1 -- ";

        $response = $this->actingAs($this->admin)->get(route('areas.index', ['search' => $payload]));
        $response->assertStatus(200);

        $responsePart = $this->actingAs($this->admin)->get(route('part-numbers.index', ['search' => $payload]));
        $responsePart->assertStatus(200);

        $responseConsume = $this->actingAs($this->admin)->get(route('consume.index', ['search' => $payload]));
        $responseConsume->assertStatus(200);

        $responseReport = $this->actingAs($this->admin)->get(route('reports.index', ['search' => $payload]));
        $responseReport->assertStatus(200);
    }

    public function test_sql_injection_payload_in_dashboard_drill_down_is_safe(): void
    {
        $payload = "1' UNION SELECT 1,2,3,4,5,6 -- ";

        $response = $this->actingAs($this->admin)->getJson('/dashboard/drill-down?type=part&id=' . urlencode($payload));
        $response->assertStatus(200);
    }

    public function test_sql_injection_payload_in_route_binding_returns_404(): void
    {
        $payload = "01JNONEXISTENT' OR '1'='1";

        $response = $this->actingAs($this->admin)->delete('/areas/' . urlencode($payload));
        $response->assertStatus(404);
    }
}

