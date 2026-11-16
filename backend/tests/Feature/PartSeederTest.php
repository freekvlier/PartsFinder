<?php

use App\Condition;
use App\Models\Brand;
use App\Models\Part;
use Database\Seeders\PartSeeder;

beforeEach(fn () => $this->seed(PartSeeder::class));

test('it seeds at least 40 parts across at least four brands', function () {
    expect(Part::count())->toBeGreaterThanOrEqual(40)
        ->and(Brand::count())->toBeGreaterThanOrEqual(4);
});

test('it seeds both conditions', function () {
    expect(Part::where('condition', Condition::New)->exists())->toBeTrue()
        ->and(Part::where('condition', Condition::Used)->exists())->toBeTrue();
});

test('it seeds several parts that are out of stock', function () {
    expect(Part::where('stock_quantity', 0)->count())->toBeGreaterThanOrEqual(3);
});

test('it seeds at least five parts with colours', function () {
    expect(Part::has('colours')->count())->toBeGreaterThanOrEqual(5);
});

test('it seeds a new and a used part with the same OEM number', function () {
    $shared = Part::selectRaw('oem')->groupBy('oem')->havingRaw('count(*) > 1')->exists();

    expect($shared)->toBeTrue();
});

test('every part has a positive price in integer cents', function () {
    expect(Part::where('price_cents', '<=', 0)->exists())->toBeFalse()
        ->and(Part::min('price_cents'))->toBeInt();
});
