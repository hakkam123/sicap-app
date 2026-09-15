<?php

namespace Database\Factories;

use App\Models\PartNumber;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PartNumber>
 */
class PartNumberFactory extends Factory
{
    protected $model = PartNumber::class;

    public function definition(): array
    {
        return [
            'pn_baan' => strtoupper(fake()->unique()->lexify('PN-????-###')),
            'description' => fake()->sentence(),
        ];
    }
}

