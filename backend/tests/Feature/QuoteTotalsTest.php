<?php

use App\Models\Part;
use App\Models\Quote;
use App\Models\QuoteLine;

test('a quote of a workshop with 10% discount returns subtotal, discount, vat and total', function () {
    $workshop = actingAsWorkshop(10);
    $quote = Quote::factory()->for($workshop)->create();
    $brakeDisc = Part::factory()->create(['price_cents' => 12999, 'stock_quantity' => 10]);
    $lamp = Part::factory()->create(['price_cents' => 4950, 'stock_quantity' => 10]);
    QuoteLine::factory()->for($quote)->for($brakeDisc)->create(['quantity' => 2]);
    QuoteLine::factory()->for($quote)->for($lamp)->create(['quantity' => 1]);

    $this->getJson("/api/quotes/{$quote->id}")
        ->assertOk()
        ->assertJsonPath('data.discount_percentage', 10)
        ->assertJsonPath('data.vat_percentage', 21)
        ->assertJsonPath('data.totals', [
            'subtotal_cents' => 30948,
            'discount_cents' => 3095,
            'vat_cents' => 5849,
            'total_cents' => 33702,
        ]);
});

test('a workshop without discount gets no discount', function () {
    $workshop = actingAsWorkshop(0);
    $quote = Quote::factory()->for($workshop)->create();
    $part = Part::factory()->create(['price_cents' => 10000, 'stock_quantity' => 5]);
    QuoteLine::factory()->for($quote)->for($part)->create();

    $this->getJson("/api/quotes/{$quote->id}")
        ->assertJsonPath('data.totals', [
            'subtotal_cents' => 10000,
            'discount_cents' => 0,
            'vat_cents' => 2100,
            'total_cents' => 12100,
        ]);
});

test('every response after a change carries the recalculated totals', function () {
    $workshop = actingAsWorkshop(10);
    $quote = Quote::factory()->for($workshop)->create();
    $part = Part::factory()->create(['price_cents' => 10000, 'stock_quantity' => 10]);

    $this->postJson("/api/quotes/{$quote->id}/lines", ['part_id' => $part->id, 'quantity' => 1])
        ->assertJsonPath('data.totals.subtotal_cents', 10000);

    $line = $quote->lines()->first();
    $this->patchJson("/api/quotes/{$quote->id}/lines/{$line->id}", ['quantity' => 3])
        ->assertJsonPath('data.totals.subtotal_cents', 30000)
        ->assertJsonPath('data.totals.discount_cents', 3000)
        ->assertJsonPath('data.totals.vat_cents', 5670)
        ->assertJsonPath('data.totals.total_cents', 32670);

    $this->deleteJson("/api/quotes/{$quote->id}/lines/{$line->id}")
        ->assertJsonPath('data.totals.total_cents', 0);
});

test('a quote keeps the discount and VAT rates it was created with', function () {
    $workshop = actingAsWorkshop(10);
    $quote = Quote::factory()->for($workshop)->create();
    $part = Part::factory()->create(['price_cents' => 10000, 'stock_quantity' => 5]);
    QuoteLine::factory()->for($quote)->for($part)->create();

    $workshop->update(['discount_percentage' => 50]);
    config(['quotes.vat_percentage' => 9]);

    $this->getJson("/api/quotes/{$quote->id}")
        ->assertJsonPath('data.discount_percentage', 10)
        ->assertJsonPath('data.vat_percentage', 21)
        ->assertJsonPath('data.totals.discount_cents', 1000)
        ->assertJsonPath('data.totals.vat_cents', 1890);
});

test('a new quote takes the current rates of the workshop and the configuration', function () {
    $workshop = actingAsWorkshop(10);
    config(['quotes.vat_percentage' => 9]);

    $this->postJson('/api/quotes')
        ->assertCreated()
        ->assertJsonPath('data.discount_percentage', 10)
        ->assertJsonPath('data.vat_percentage', 9);

    expect($workshop->quotes()->first()->vat_percentage)->toBe(9.0);
});

test('a draft quote follows the current part price', function () {
    $workshop = actingAsWorkshop();
    $quote = Quote::factory()->for($workshop)->create();
    $part = Part::factory()->create(['price_cents' => 10000, 'stock_quantity' => 5]);
    QuoteLine::factory()->for($quote)->for($part)->create(['quantity' => 2]);

    $part->update(['price_cents' => 15000]);

    $this->getJson("/api/quotes/{$quote->id}")
        ->assertJsonPath('data.lines.0.unit_price_cents', 15000)
        ->assertJsonPath('data.totals.subtotal_cents', 30000);
});
