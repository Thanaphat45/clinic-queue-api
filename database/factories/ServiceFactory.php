<?php

namespace Database\Factories;

use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Service> */
class ServiceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->lexify('?')),
            'name' => fake()->words(2, true),
            'avg_minutes' => 10,
            'is_open' => true,
        ];
    }
}
