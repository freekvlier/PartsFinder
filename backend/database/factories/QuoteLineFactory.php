<?php

namespace Database\Factories;

use App\Models\Part;
use App\Models\Quote;
use App\Models\QuoteLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuoteLine>
 */
class QuoteLineFactory extends Factory
{
    /**
     * Define the default state of the model.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'quote_id' => Quote::factory(),
            'part_id' => Part::factory(),
            'colour_id' => null,
            'quantity' => 1,
            'backorder' => false,
        ];
    }
}
