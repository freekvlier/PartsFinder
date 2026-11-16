<?php

use App\Models\Part;
use App\Models\Quote;
use App\Models\QuoteLine;

test('creating a quote returns an empty draft with totals of zero', function () {
    $workshop = actingAsWorkshop();

    $this->postJson('/api/quotes')
        ->assertCreated()
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.submitted_at', null)
        ->assertJsonPath('data.discount_percentage', 0)
        ->assertJsonPath('data.vat_percentage', 21)
        ->assertJsonPath('data.lines', [])
        ->assertJsonPath('data.warnings', [])
        ->assertJsonPath('data.totals', [
            'subtotal_cents' => 0,
            'discount_cents' => 0,
            'vat_cents' => 0,
            'total_cents' => 0,
        ]);

    expect($workshop->quotes()->count())->toBe(1);
});

test('showing a quote returns its lines and totals', function () {
    $workshop = actingAsWorkshop();
    $quote = Quote::factory()->for($workshop)->create();
    $part = Part::factory()->create([
        'name' => 'DAF Brake disc',
        'oem' => '2108022',
        'price_cents' => 21000,
        'stock_quantity' => 5,
    ]);
    QuoteLine::factory()->for($quote)->for($part)->create(['quantity' => 2]);

    $this->getJson("/api/quotes/{$quote->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $quote->id)
        ->assertJsonCount(1, 'data.lines')
        ->assertJsonPath('data.lines.0.name', 'DAF Brake disc')
        ->assertJsonPath('data.lines.0.oem', '2108022')
        ->assertJsonPath('data.lines.0.quantity', 2)
        ->assertJsonPath('data.lines.0.unit_price_cents', 21000)
        ->assertJsonPath('data.lines.0.line_total_cents', 42000)
        ->assertJsonPath('data.lines.0.colour', null)
        ->assertJsonPath('data.totals.subtotal_cents', 42000);
});

test('a workshop cannot see another workshop\'s quote', function () {
    $other = Quote::factory()->create();
    actingAsWorkshop();

    $this->getJson("/api/quotes/{$other->id}")->assertNotFound();
});

test('a workshop cannot change another workshop\'s quote', function () {
    $other = Quote::factory()->create();
    $line = QuoteLine::factory()->for($other)->create();
    $part = Part::factory()->create(['stock_quantity' => 5]);
    actingAsWorkshop();

    $this->postJson("/api/quotes/{$other->id}/lines", ['part_id' => $part->id, 'quantity' => 1])->assertNotFound();
    $this->patchJson("/api/quotes/{$other->id}/lines/{$line->id}", ['quantity' => 2])->assertNotFound();
    $this->deleteJson("/api/quotes/{$other->id}/lines/{$line->id}")->assertNotFound();
    $this->postJson("/api/quotes/{$other->id}/submit")->assertNotFound();

    expect($other->fresh()->isSubmitted())->toBeFalse()
        ->and($line->fresh()->quantity)->toBe(1);
});

test('a line of another quote cannot be reached through this quote', function () {
    $workshop = actingAsWorkshop();
    $quote = Quote::factory()->for($workshop)->create();
    $otherQuote = Quote::factory()->for($workshop)->create();
    $line = QuoteLine::factory()->for($otherQuote)->create();

    $this->patchJson("/api/quotes/{$quote->id}/lines/{$line->id}", ['quantity' => 2])->assertNotFound();
    $this->deleteJson("/api/quotes/{$quote->id}/lines/{$line->id}")->assertNotFound();
});

test('an unknown quote is a 404', function () {
    actingAsWorkshop();

    $this->getJson('/api/quotes/999')->assertNotFound();
});
