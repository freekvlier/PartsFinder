<?php

use App\Services\QuoteCalculator;

test('it calculates subtotal, discount, vat and total for a workshop with 10% discount', function () {
    $totals = QuoteCalculator::totals([25998, 4950], 10, 21);

    expect($totals)->toBe(['subtotal' => 30948, 'discount' => 3095, 'vat' => 5849, 'total' => 33702]);
});

test('a workshop without discount pays the subtotal plus vat', function () {
    $totals = QuoteCalculator::totals([10000], 0, 21);

    expect($totals)->toBe(['subtotal' => 10000, 'discount' => 0, 'vat' => 2100, 'total' => 12100]);
});

test('every step is rounded half up to a whole cent', function () {
    $totals = QuoteCalculator::totals([1005], 10, 21);

    expect($totals['discount'])->toBe(101)
        ->and($totals['vat'])->toBe(190)
        ->and($totals['total'])->toBe(1005 - 101 + 190);
});

test('an empty quote has totals of zero', function () {
    expect(QuoteCalculator::totals([], 10, 21))
        ->toBe(['subtotal' => 0, 'discount' => 0, 'vat' => 0, 'total' => 0]);
});

test('fractional discount percentages are supported', function () {
    $totals = QuoteCalculator::totals([10000], 12.5, 21);

    expect($totals['discount'])->toBe(1250)
        ->and($totals['vat'])->toBe(1838);
});

test('fractional VAT rates are supported', function () {
    $totals = QuoteCalculator::totals([10000], 0, 5.5);

    expect($totals['vat'])->toBe(550)
        ->and($totals['total'])->toBe(10550);
});
