<?php

namespace Database\Factories;

use App\Models\ImportLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ImportLog>
 */
class ImportLogFactory extends Factory
{
    protected $model = ImportLog::class;

    public function definition(): array
    {
        $total = fake()->numberBetween(10, 100);
        $success = fake()->numberBetween(0, $total);
        $failed = $total - $success;

        return [
            'feature' => fake()->randomElement(['area', 'machine', 'part_number', 'mapping', 'consume']),
            'filename' => fake()->word() . '.xlsx',
            'status' => 'completed',
            'error_message' => null,
            'error_details' => null,
            'total_rows' => $total,
            'processed_rows' => $total,
            'success_rows' => $success,
            'failed_rows' => $failed,
            'started_at' => now()->subMinutes(5),
            'finished_at' => now(),
            'user_id' => User::factory(),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'total_rows' => 0,
            'processed_rows' => 0,
            'success_rows' => 0,
            'failed_rows' => 0,
            'started_at' => null,
            'finished_at' => null,
        ]);
    }

    public function processing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'processing',
            'total_rows' => 50,
            'processed_rows' => 20,
            'success_rows' => 20,
            'failed_rows' => 0,
            'started_at' => now(),
            'finished_at' => null,
        ]);
    }

    public function success(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'success',
            'total_rows' => 50,
            'processed_rows' => 50,
            'success_rows' => 50,
            'failed_rows' => 0,
            'started_at' => now()->subMinutes(2),
            'finished_at' => now(),
            'error_message' => null,
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'total_rows' => 50,
            'processed_rows' => 10,
            'success_rows' => 5,
            'failed_rows' => 5,
            'started_at' => now()->subMinutes(2),
            'finished_at' => now(),
            'error_message' => 'Invalid header format or missing required columns',
            'error_details' => ['Row 6: Invalid part number', 'Row 7: Duplicate entry'],
        ]);
    }
}

