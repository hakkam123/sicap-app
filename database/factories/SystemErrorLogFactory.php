<?php

namespace Database\Factories;

use App\Models\SystemErrorLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SystemErrorLog>
 */
class SystemErrorLogFactory extends Factory
{
    protected $model = SystemErrorLog::class;

    public function definition(): array
    {
        return [
            'error_type' => fake()->randomElement(['system', 'api', 'import', 'job']),
            'status_code' => fake()->randomElement([400, 404, 422, 500]),
            'severity' => fake()->randomElement(['critical', 'warning', 'info']),
            'feature' => fake()->randomElement(['consume', 'part_number', 'area', 'machine', 'mapping', 'user', 'report', 'sync_api', 'auth', 'system']),
            'message' => fake()->sentence(),
            'exception_class' => \Exception::class,
            'file' => 'app/Http/Controllers/ConsumeController.php',
            'line' => fake()->numberBetween(10, 200),
            'url' => fake()->url(),
            'method' => fake()->randomElement(['GET', 'POST', 'PUT', 'DELETE']),
            'request_payload' => json_encode(['sample_key' => 'sample_value']),
            'stack_trace' => fake()->paragraphs(2, true),
            'user_id' => null,
            'user_ip' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'status' => 'unresolved',
            'resolved_at' => null,
            'resolved_by' => null,
            'resolution_notes' => null,
        ];
    }

    public function unresolved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'unresolved',
            'resolved_at' => null,
            'resolved_by' => null,
            'resolution_notes' => null,
        ]);
    }

    public function resolved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'resolved',
            'resolved_at' => now(),
            'resolved_by' => User::factory()->admin(),
            'resolution_notes' => 'Fixed and verified in production patch.',
        ]);
    }

    public function critical(): static
    {
        return $this->state(fn (array $attributes) => [
            'severity' => 'critical',
            'status_code' => 500,
        ]);
    }
}

