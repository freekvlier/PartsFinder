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
     * Define the default state of the model: a draft with the rates of its workshop and the configured VAT.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workshop_id' => Workshop::factory(),
            'status' => QuoteStatus::Draft,
            'discount_percentage' => fn (array $attributes) => Workshop::find($attributes['workshop_id'])
                ->discount_percentage,
            'vat_percentage' => config('quotes.vat_percentage'),
        ];
    }
}
