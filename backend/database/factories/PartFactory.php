<?php

namespace Database\Factories;

use App\Condition;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Colour;
use App\Models\Part;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Part>
 */
class PartFactory extends Factory
{
    /**
     * Define the default state of the model.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'brand_id' => Brand::factory(),
            'category_id' => Category::factory(),
            'name' => fake()->words(3, true),
            'oem' => fake()->numerify('#######'),
            'condition' => fake()->randomElement(Condition::cases()),
            'price_cents' => fake()->numberBetween(500, 250000),
            'stock_quantity' => fake()->numberBetween(0, 20),
            'image' => '/img/placeholder-part.svg',
        ];
    }

    /**
     * Attach the given colours to the created part, creating missing ones.
     *
     * @param  list<string>  $names  The colour names, for example `['white', 'red']`.
     */
    public function withColours(array $names): static
    {
        return $this->afterCreating(function (Part $part) use ($names) {
            $part->colours()->attach(
                collect($names)->map(fn (string $name) => Colour::firstOrCreate(['name' => $name])->id),
            );
        });
    }
}
