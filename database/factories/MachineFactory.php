<?php

namespace Database\Factories;

use App\Models\Area;
use App\Models\Machine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Machine>
 */
class MachineFactory extends Factory
{
    protected $model = Machine::class;

    public function definition(): array
    {
        return [
            'area_id' => Area::factory(),
            'code' => strtoupper(fake()->unique()->lexify('M-???')),
            'name' => 'Machine ' . fake()->numerify('###'),
            'description' => fake()->sentence(),
        ];
    }
}

