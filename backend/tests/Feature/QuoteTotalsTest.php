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
        ->assertJsonPath('data.discountPercentage', 10)
        ->assertJsonPath('data.vatPercentage', 21)
        ->assertJsonPath('data.totals', [
            'subtotalCents' => 30948,
            'discountCents' => 3095,
            'vatCents' => 5849,
            'totalCents' => 33702,
        ]);
});

test('a workshop without discount gets no discount', function () {
    $workshop = actingAsWorkshop(0);
    $quote = Quote::factory()->for($workshop)->create();
    $part = Part::factory()->create(['price_cents' => 10000, 'stock_quantity' => 5]);
    QuoteLine::factory()->for($quote)->for($part)->create();

    $this->getJson("/api/quotes/{$quote->id}")
        ->assertJsonPath('data.totals', [
            'subtotalCents' => 10000,
            'discountCents' => 0,
            'vatCents' => 2100,
            'totalCents' => 12100,
        ]);
});

test('every response after a change carries the recalculated totals', function () {
    $workshop = actingAsWorkshop(10);
    $quote = Quote::factory()->for($workshop)->create();
    $part = Part::factory()->create(['price_cents' => 10000, 'stock_quantity' => 10]);

    $this->postJson("/api/quotes/{$quote->id}/lines", ['partId' => $part->id, 'quantity' => 1])
        ->assertJsonPath('data.totals.subtotalCents', 10000);

    $line = $quote->lines()->first();
    $this->patchJson("/api/quotes/{$quote->id}/lines/{$line->id}", ['quantity' => 3])
        ->assertJsonPath('data.totals.subtotalCents', 30000)
        ->assertJsonPath('data.totals.discountCents', 3000)
        ->assertJsonPath('data.totals.vatCents', 5670)
        ->assertJsonPath('data.totals.totalCents', 32670);

    $this->deleteJson("/api/quotes/{$quote->id}/lines/{$line->id}")
        ->assertJsonPath('data.totals.totalCents', 0);
});

test('a draft quote follows the current part price', function () {
    $workshop = actingAsWorkshop();
    $quote = Quote::factory()->for($workshop)->create();
    $part = Part::factory()->create(['price_cents' => 10000, 'stock_quantity' => 5]);
    QuoteLine::factory()->for($quote)->for($part)->create(['quantity' => 2]);

    $part->update(['price_cents' => 15000]);

    $this->getJson("/api/quotes/{$quote->id}")
        ->assertJsonPath('data.lines.0.unitPriceCents', 15000)
        ->assertJsonPath('data.totals.subtotalCents', 30000);
});
