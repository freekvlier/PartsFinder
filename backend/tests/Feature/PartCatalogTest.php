<?php

use App\Condition;
use App\Models\Colour;
use App\Models\Part;
use Illuminate\Database\QueryException;

test('a part is in stock when its stock quantity is above zero', function () {
    $inStock = Part::factory()->create(['stock_quantity' => 3]);
    $soldOut = Part::factory()->create(['stock_quantity' => 0]);

    expect($inStock->in_stock)->toBeTrue()
        ->and($soldOut->in_stock)->toBeFalse();
});

test('a part has the colours it was given and a part without colours has none', function () {
    $coloured = Part::factory()->withColours(['white', 'red'])->create();
    $plain = Part::factory()->create();

    expect($coloured->colours->pluck('name')->all())->toEqualCanonicalizing(['white', 'red'])
        ->and($plain->colours)->toBeEmpty();
});

test('parts share colour records', function () {
    Part::factory()->count(2)->withColours(['white'])->create();

    expect(Colour::count())->toBe(1);
});

test('a colour can only be added once to a part', function () {
    $part = Part::factory()->withColours(['white'])->create();

    expect(fn () => $part->colours()->attach(Colour::firstWhere('name', 'white')))
        ->toThrow(QueryException::class);
});

test('different parts can share an OEM number, for example a new and a used version', function () {
    Part::factory()->create(['oem' => '2119711', 'condition' => Condition::New]);
    Part::factory()->create(['oem' => '2119711', 'condition' => Condition::Used]);

    expect(Part::where('oem', '2119711')->count())->toBe(2);
});

test('the condition is cast to an enum and the price is stored as integer cents', function () {
    $part = Part::factory()->create(['condition' => 'used', 'price_cents' => 79500]);

    expect($part->fresh()->condition)->toBe(Condition::Used)
        ->and($part->fresh()->price_cents)->toBe(79500);
});

test('a part belongs to a brand and a category', function () {
    $part = Part::factory()->create();

    expect($part->brand->parts->pluck('id')->all())->toBe([$part->id])
        ->and($part->category->parts->pluck('id')->all())->toBe([$part->id]);
});
