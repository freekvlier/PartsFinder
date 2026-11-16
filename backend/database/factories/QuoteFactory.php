<?php

namespace Database\Factories;

use App\Models\Quote;
use App\Models\Workshop;
use App\QuoteStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Quote>
 */
class QuoteFactory extends Factory
{
    /**
     * Define the default state of the model.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workshop_id' => Workshop::factory(),
            'status' => QuoteStatus::Draft,
        ];
    }
}
