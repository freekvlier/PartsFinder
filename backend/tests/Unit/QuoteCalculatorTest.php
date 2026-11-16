<?php

use App\Services\QuoteCalculator;

test('it calculates subtotal, discount, vat and total for a workshop with 10% discount', function () {
    // 2 × 129.99 + 49.50 = 309.48; discount 30.948 → 30.95; vat 21% of 278.53 = 58.4913 → 58.49; total 337.02
    $totals = (new QuoteCalculator)->totals([25998, 4950], 10);

    expect($totals)->toBe(['subtotal' => 30948, 'discount' => 3095, 'vat' => 5849, 'total' => 33702]);
});

test('a workshop without discount pays the subtotal plus vat', function () {
    $totals = (new QuoteCalculator)->totals([10000], 0);

    expect($totals)->toBe(['subtotal' => 10000, 'discount' => 0, 'vat' => 2100, 'total' => 12100]);
});

test('every step is rounded half up to a whole cent', function () {
    // discount 10% of 1005 = 100.5 → 101; vat 21% of 904 = 189.84 → 190
    $totals = (new QuoteCalculator)->totals([1005], 10);

    expect($totals['discount'])->toBe(101)
        ->and($totals['vat'])->toBe(190)
        ->and($totals['total'])->toBe(1005 - 101 + 190);
});

test('an empty quote has totals of zero', function () {
    expect((new QuoteCalculator)->totals([], 10))
        ->toBe(['subtotal' => 0, 'discount' => 0, 'vat' => 0, 'total' => 0]);
});

test('fractional discount percentages are supported', function () {
    // 12.5% of 10000 = 1250; vat of 8750 = 1837.5 → 1838
    $totals = (new QuoteCalculator)->totals([10000], 12.5);

    expect($totals['discount'])->toBe(1250)
        ->and($totals['vat'])->toBe(1838);
});
