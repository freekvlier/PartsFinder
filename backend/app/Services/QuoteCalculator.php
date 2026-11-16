<?php

namespace App\Services;

class QuoteCalculator
{
    /**
     * Calculate the quote totals in integer cents, rounding half up to a cent at every step.
     *
     * @param  list<int>  $lineTotals  The total of every line (unit price × quantity), in cents.
     * @param  float  $discountPercentage  The discount, for example 10 for 10%.
     * @param  float  $vatPercentage  The VAT rate, for example 21 for 21%.
     * @return array{subtotal: int, discount: int, vat: int, total: int}
     */
    public static function totals(array $lineTotals, float $discountPercentage, float $vatPercentage): array
    {
        $subtotal = array_sum($lineTotals);
        $discount = self::share($subtotal, $discountPercentage);
        $vat = self::share($subtotal - $discount, $vatPercentage);

        return [
            'subtotal' => $subtotal,
            'discount' => $discount,
            'vat' => $vat,
            'total' => $subtotal - $discount + $vat,
        ];
    }

    /**
     * Take a percentage of an amount, rounded half up to a whole cent, using integer maths in basis points.
     *
     * @param  int  $amount  The amount in cents.
     * @param  float  $percentage  The share in percent.
     * @return int The share in cents.
     */
    private static function share(int $amount, float $percentage): int
    {
        return intdiv($amount * (int) round($percentage * 100) + 5000, 10000);
    }
}
