<?php

namespace Database\Factories;

use App\Models\Area;
use App\Models\Consume;
use App\Models\Machine;
use App\Models\PartNumber;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Consume>
 */
class ConsumeFactory extends Factory
{
    protected $model = Consume::class;

    public function definition(): array
    {
        return [
            'part_number_id' => PartNumber::factory(),
            'area_id' => Area::factory(),
            'machine_id' => Machine::factory(),
            'quantity' => fake()->numberBetween(1, 50),
            'amount' => fake()->randomFloat(2, 1000, 500000),
            'consumed_at' => fake()->dateTimeBetween('-1 month', 'now'),
            'source' => fake()->randomElement(['excel', 'api', 'manual']),
            'created_by' => User::factory(),
        ];
    }

    public function excel(): static
    {
        return $this->state(fn (array $attributes) => [
            'source' => 'excel',
        ]);
    }

    public function api(): static
    {
        return $this->state(fn (array $attributes) => [
            'source' => 'api',
        ]);
    }

    public function manual(): static
    {
        return $this->state(fn (array $attributes) => [
            'source' => 'manual',
        ]);
    }
}

