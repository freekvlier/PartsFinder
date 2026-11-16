<?php

use App\Models\Part;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\Workshop;
use App\QuoteStatus;

beforeEach(function () {
    $this->workshop = actingAsWorkshop();
    $this->quote = Quote::factory()->for($this->workshop)->create();
    $this->url = "/api/quotes/{$this->quote->id}/lines";
});

test('it adds a part to the quote', function () {
    $part = Part::factory()->create(['name' => 'DAF Brake disc', 'price_cents' => 21000, 'stock_quantity' => 5]);

    $this->postJson($this->url, ['partId' => $part->id, 'quantity' => 2])
        ->assertCreated()
        ->assertJsonCount(1, 'data.lines')
        ->assertJsonPath('data.lines.0.partId', $part->id)
        ->assertJsonPath('data.lines.0.quantity', 2)
        ->assertJsonPath('data.lines.0.backorder', false)
        ->assertJsonPath('data.warnings', []);

    expect($this->quote->lines()->count())->toBe(1);
});

test('adding a part that is already on the quote increases the quantity instead of adding a second line', function () {
    $part = Part::factory()->create(['stock_quantity' => 10]);

    $this->postJson($this->url, ['partId' => $part->id, 'quantity' => 2])->assertCreated();
    $this->postJson($this->url, ['partId' => $part->id, 'quantity' => 3])
        ->assertJsonCount(1, 'data.lines')
        ->assertJsonPath('data.lines.0.quantity', 5);

    expect($this->quote->lines()->count())->toBe(1);
});

test('the same coloured part in the same colour merges, in another colour it is a separate line', function () {
    $part = Part::factory()->withColours(['white', 'red'])->create(['stock_quantity' => 10]);

    $this->postJson($this->url, ['partId' => $part->id, 'quantity' => 1, 'colour' => 'white']);
    $this->postJson($this->url, ['partId' => $part->id, 'quantity' => 2, 'colour' => 'white']);
    $this->postJson($this->url, ['partId' => $part->id, 'quantity' => 1, 'colour' => 'red'])
        ->assertJsonCount(2, 'data.lines')
        ->assertJsonPath('data.lines.0.colour', 'white')
        ->assertJsonPath('data.lines.0.quantity', 3)
        ->assertJsonPath('data.lines.1.colour', 'red')
        ->assertJsonPath('data.lines.1.quantity', 1);
});

test('a part with colours needs one of its colours', function () {
    $part = Part::factory()->withColours(['white', 'red'])->create(['stock_quantity' => 5]);

    $this->postJson($this->url, ['partId' => $part->id, 'quantity' => 1])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('colour');
    $this->postJson($this->url, ['partId' => $part->id, 'quantity' => 1, 'colour' => 'green'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('colour');

    expect($this->quote->lines()->count())->toBe(0);
});

test('a colour of another part is not accepted', function () {
    Part::factory()->withColours(['green'])->create();
    $part = Part::factory()->withColours(['white'])->create(['stock_quantity' => 5]);

    $this->postJson($this->url, ['partId' => $part->id, 'quantity' => 1, 'colour' => 'green'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('colour');
});

test('a part without colours does not take a colour', function () {
    $part = Part::factory()->create(['stock_quantity' => 5]);

    $this->postJson($this->url, ['partId' => $part->id, 'quantity' => 1, 'colour' => 'white'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('colour');
});

test('the quantity must be an integer of 1 or more', function (mixed $quantity) {
    $part = Part::factory()->create(['stock_quantity' => 5]);

    $this->postJson($this->url, ['partId' => $part->id, 'quantity' => $quantity])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('quantity');
})->with([0, -1, 'abc', 1.5, null]);

test('the part must exist', function () {
    $this->postJson($this->url, ['partId' => 9999, 'quantity' => 1])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('partId');
});

test('a part in stock cannot be quoted beyond its stock', function () {
    $part = Part::factory()->create(['stock_quantity' => 3]);

    $this->postJson($this->url, ['partId' => $part->id, 'quantity' => 3])->assertCreated();
    $this->postJson($this->url, ['partId' => $part->id, 'quantity' => 1])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('quantity');

    expect($this->quote->lines()->first()->quantity)->toBe(3);
});

test('the stock is shared by all colours of a part', function () {
    $part = Part::factory()->withColours(['white', 'red'])->create(['stock_quantity' => 3]);

    $this->postJson($this->url, ['partId' => $part->id, 'quantity' => 2, 'colour' => 'white'])->assertCreated();
    $this->postJson($this->url, ['partId' => $part->id, 'quantity' => 2, 'colour' => 'red'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('quantity');
    $this->postJson($this->url, ['partId' => $part->id, 'quantity' => 1, 'colour' => 'red'])->assertCreated();
});

test('a part without stock can be quoted in any quantity and is flagged as backorder with a warning', function () {
    $part = Part::factory()->create(['stock_quantity' => 0]);
    $inStock = Part::factory()->create(['stock_quantity' => 5]);

    $this->postJson($this->url, ['partId' => $inStock->id, 'quantity' => 1]);
    $response = $this->postJson($this->url, ['partId' => $part->id, 'quantity' => 40])
        ->assertCreated()
        ->assertJsonPath('data.lines.0.backorder', false)
        ->assertJsonPath('data.lines.1.backorder', true)
        ->assertJsonPath('data.lines.1.quantity', 40)
        ->assertJsonCount(1, 'data.warnings')
        ->assertJsonPath('data.warnings.0.code', 'backorder');

    $backorderLineId = $this->quote->lines()->where('part_id', $part->id)->value('id');
    expect($response->json('data.warnings.0.lineIds'))->toBe([$backorderLineId]);
});

test('it changes the quantity of a line', function () {
    $part = Part::factory()->create(['stock_quantity' => 5]);
    $line = QuoteLine::factory()->for($this->quote)->for($part)->create(['quantity' => 1]);

    $this->patchJson("{$this->url}/{$line->id}", ['quantity' => 4])
        ->assertOk()
        ->assertJsonPath('data.lines.0.quantity', 4);
});

test('changing the quantity of a line beyond the stock is rejected', function () {
    $part = Part::factory()->create(['stock_quantity' => 5]);
    $line = QuoteLine::factory()->for($this->quote)->for($part)->create(['quantity' => 1]);

    $this->patchJson("{$this->url}/{$line->id}", ['quantity' => 6])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('quantity');

    expect($line->fresh()->quantity)->toBe(1);
});

test('an invalid quantity on a line is rejected', function (mixed $quantity) {
    $line = QuoteLine::factory()->for($this->quote)->for(Part::factory()->create(['stock_quantity' => 5]))->create();

    $this->patchJson("{$this->url}/{$line->id}", ['quantity' => $quantity])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('quantity');
})->with([0, -2, 'abc']);

test('it changes the colour of a line', function () {
    $part = Part::factory()->withColours(['white', 'red'])->create(['stock_quantity' => 5]);
    $this->postJson($this->url, ['partId' => $part->id, 'quantity' => 2, 'colour' => 'white']);
    $line = $this->quote->lines()->first();

    $this->patchJson("{$this->url}/{$line->id}", ['colour' => 'red'])
        ->assertOk()
        ->assertJsonPath('data.lines.0.colour', 'red')
        ->assertJsonPath('data.lines.0.quantity', 2);
});

test('changing the colour of a line to a colour that is already on the quote merges the lines', function () {
    $part = Part::factory()->withColours(['white', 'red'])->create(['stock_quantity' => 10]);
    $this->postJson($this->url, ['partId' => $part->id, 'quantity' => 2, 'colour' => 'white']);
    $this->postJson($this->url, ['partId' => $part->id, 'quantity' => 3, 'colour' => 'red']);
    $white = $this->quote->lines()->first();

    $this->patchJson("{$this->url}/{$white->id}", ['colour' => 'red'])
        ->assertOk()
        ->assertJsonCount(1, 'data.lines')
        ->assertJsonPath('data.lines.0.colour', 'red')
        ->assertJsonPath('data.lines.0.quantity', 5);
});

test('changing a line to a colour the part does not have is rejected', function () {
    $part = Part::factory()->withColours(['white'])->create(['stock_quantity' => 5]);
    $this->postJson($this->url, ['partId' => $part->id, 'quantity' => 1, 'colour' => 'white']);
    $line = $this->quote->lines()->first();

    $this->patchJson("{$this->url}/{$line->id}", ['colour' => 'green'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('colour');
});

test('it removes a line', function () {
    $keep = QuoteLine::factory()->for($this->quote)->create();
    $remove = QuoteLine::factory()->for($this->quote)->create();

    $this->deleteJson("{$this->url}/{$remove->id}")
        ->assertOk()
        ->assertJsonCount(1, 'data.lines')
        ->assertJsonPath('data.lines.0.id', $keep->id);

    expect(QuoteLine::find($remove->id))->toBeNull();
});

test('a submitted quote cannot be changed and answers 409', function () {
    $part = Part::factory()->create(['stock_quantity' => 5]);
    $line = QuoteLine::factory()->for($this->quote)->for($part)->create(['quantity' => 1]);
    $this->quote->update([
        'status' => QuoteStatus::Submitted,
        'subtotal_cents' => 0,
        'discount_cents' => 0,
        'vat_cents' => 0,
        'total_cents' => 0,
    ]);

    $this->postJson($this->url, ['partId' => $part->id, 'quantity' => 1])->assertConflict();
    $this->patchJson("{$this->url}/{$line->id}", ['quantity' => 2])->assertConflict();
    $this->deleteJson("{$this->url}/{$line->id}")->assertConflict();

    expect($line->fresh()->quantity)->toBe(1)
        ->and($this->quote->lines()->count())->toBe(1);
});

test('lines added by another workshop do not appear on this quote', function () {
    $other = Quote::factory()->for(Workshop::factory())->create();
    QuoteLine::factory()->for($other)->create();

    $this->getJson("/api/quotes/{$this->quote->id}")->assertJsonCount(0, 'data.lines');
});
