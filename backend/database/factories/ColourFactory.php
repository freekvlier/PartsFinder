<?php

namespace Database\Factories;

use App\Models\Colour;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Colour>
 */
class ColourFactory extends Factory
{
    /**
     * Define the default state of the model.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->safeColorName(),
        ];
    }
}
