<?php

use App\Models\Part;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\QuoteStatus;

beforeEach(function () {
    $this->workshop = actingAsWorkshop(10);
    $this->quote = Quote::factory()->for($this->workshop)->create();
    $this->url = "/api/quotes/{$this->quote->id}/submit";
});

test('a quote without lines cannot be submitted', function () {
    $this->postJson($this->url)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('lines');

    expect($this->quote->fresh()->status)->toBe(QuoteStatus::Draft);
});

test('submitting locks the quote and stores the status, timestamp, unit prices and totals', function () {
    $part = Part::factory()->create(['price_cents' => 12999, 'stock_quantity' => 10]);
    $lamp = Part::factory()->create(['price_cents' => 4950, 'stock_quantity' => 10]);
    QuoteLine::factory()->for($this->quote)->for($part)->create(['quantity' => 2]);
    QuoteLine::factory()->for($this->quote)->for($lamp)->create(['quantity' => 1]);

    $this->postJson($this->url)
        ->assertOk()
        ->assertJsonPath('data.status', 'submitted')
        ->assertJsonPath('data.totals', [
            'subtotalCents' => 30948,
            'discountCents' => 3095,
            'vatCents' => 5849,
            'totalCents' => 33702,
        ]);

    $quote = $this->quote->fresh();
    expect($quote->status)->toBe(QuoteStatus::Submitted)
        ->and($quote->submitted_at)->not->toBeNull()
        ->and($quote->total_cents)->toBe(33702)
        ->and($quote->lines->pluck('unit_price_cents')->all())->toBe([12999, 4950]);
});

test('a later price change does not change a submitted quote', function () {
    $part = Part::factory()->create(['price_cents' => 10000, 'stock_quantity' => 10]);
    QuoteLine::factory()->for($this->quote)->for($part)->create(['quantity' => 2]);
    $this->postJson($this->url)->assertOk();

    $part->update(['price_cents' => 99999]);

    $this->getJson("/api/quotes/{$this->quote->id}")
        ->assertJsonPath('data.lines.0.unitPriceCents', 10000)
        ->assertJsonPath('data.lines.0.lineTotalCents', 20000)
        ->assertJsonPath('data.totals.subtotalCents', 20000)
        ->assertJsonPath('data.totals.totalCents', 21780);
});

test('a later change of the workshop discount does not change a submitted quote', function () {
    $part = Part::factory()->create(['price_cents' => 10000, 'stock_quantity' => 10]);
    QuoteLine::factory()->for($this->quote)->for($part)->create();
    $this->postJson($this->url)->assertOk();

    $this->workshop->update(['discount_percentage' => 50]);

    $this->getJson("/api/quotes/{$this->quote->id}")->assertJsonPath('data.totals.discountCents', 1000);
});

test('submitting re-checks the stock of in-stock lines and names the offending lines', function () {
    $short = Part::factory()->create(['price_cents' => 1000, 'stock_quantity' => 5]);
    $fine = Part::factory()->create(['price_cents' => 1000, 'stock_quantity' => 5]);
    $shortLine = QuoteLine::factory()->for($this->quote)->for($short)->create(['quantity' => 4]);
    QuoteLine::factory()->for($this->quote)->for($fine)->create(['quantity' => 2]);

    $short->update(['stock_quantity' => 3]);

    $this->postJson($this->url)
        ->assertUnprocessable()
        ->assertJsonValidationErrors("lines.{$shortLine->id}")
        ->assertJsonMissingValidationErrors(['lines']);

    $quote = $this->quote->fresh();
    expect($quote->status)->toBe(QuoteStatus::Draft)
        ->and($quote->submitted_at)->toBeNull()
        ->and($quote->lines->whereNotNull('unit_price_cents'))->toBeEmpty();
});

test('the stock re-check counts all colours of a part together', function () {
    $part = Part::factory()->withColours(['white', 'red'])->create(['stock_quantity' => 5]);
    $whiteId = $part->colours->firstWhere('name', 'white')->id;
    $redId = $part->colours->firstWhere('name', 'red')->id;
    $white = QuoteLine::factory()->for($this->quote)->for($part)->create(['colour_id' => $whiteId, 'quantity' => 2]);
    $red = QuoteLine::factory()->for($this->quote)->for($part)->create(['colour_id' => $redId, 'quantity' => 2]);

    $part->update(['stock_quantity' => 3]);

    $this->postJson($this->url)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(["lines.{$white->id}", "lines.{$red->id}"]);
});

test('backorder lines are not re-checked against stock', function () {
    $part = Part::factory()->create(['price_cents' => 1000, 'stock_quantity' => 0]);
    QuoteLine::factory()->for($this->quote)->for($part)->create(['quantity' => 50, 'backorder' => true]);

    $this->postJson($this->url)
        ->assertOk()
        ->assertJsonPath('data.status', 'submitted')
        ->assertJsonPath('data.warnings.0.code', 'backorder');
});

test('a submitted quote cannot be submitted again', function () {
    QuoteLine::factory()->for($this->quote)->for(Part::factory()->create(['stock_quantity' => 5]))->create();
    $this->postJson($this->url)->assertOk();

    $this->postJson($this->url)->assertConflict();
});

test('after submission every mutation answers 409 and nothing changes', function () {
    $part = Part::factory()->create(['price_cents' => 1000, 'stock_quantity' => 10]);
    $line = QuoteLine::factory()->for($this->quote)->for($part)->create(['quantity' => 2]);
    $this->postJson($this->url)->assertOk();

    $lines = "/api/quotes/{$this->quote->id}/lines";
    $this->postJson($lines, ['partId' => $part->id, 'quantity' => 1])->assertConflict();
    $this->patchJson("{$lines}/{$line->id}", ['quantity' => 5])->assertConflict();
    $this->deleteJson("{$lines}/{$line->id}")->assertConflict();

    $this->getJson("/api/quotes/{$this->quote->id}")
        ->assertOk()
        ->assertJsonCount(1, 'data.lines')
        ->assertJsonPath('data.lines.0.quantity', 2);
});

test('a new quote can be started after submitting one', function () {
    QuoteLine::factory()->for($this->quote)->for(Part::factory()->create(['stock_quantity' => 5]))->create();
    $this->postJson($this->url)->assertOk();

    $this->postJson('/api/quotes')
        ->assertCreated()
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.lines', []);
});
