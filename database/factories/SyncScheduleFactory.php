<?php

namespace Database\Factories;

use App\Models\SyncSchedule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SyncSchedule>
 */
class SyncScheduleFactory extends Factory
{
    protected $model = SyncSchedule::class;

    public function definition(): array
    {
        return [
            'time' => fake()->randomElement(['06:00', '07:00', '12:00', '16:00', '20:00']),
            'is_active' => true,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}

