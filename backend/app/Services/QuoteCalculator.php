<?php

namespace App\Services;

class QuoteCalculator
{
    /** The VAT rate in percent, applied to every quote. */
    public const VAT_PERCENTAGE = 21;

    /**
     * Calculate the quote totals in integer cents, rounding half up to a cent at every step.
     *
     * @param  list<int>  $lineTotals  The total of every line (unit price × quantity), in cents.
     * @param  float  $discountPercentage  The workshop discount, for example 10 for 10%.
     * @return array{subtotal: int, discount: int, vat: int, total: int}
     */
    public function totals(array $lineTotals, float $discountPercentage): array
    {
        $subtotal = array_sum($lineTotals);
        $discount = $this->share($subtotal, (int) round($discountPercentage * 100));
        $vat = $this->share($subtotal - $discount, self::VAT_PERCENTAGE * 100);

        return [
            'subtotal' => $subtotal,
            'discount' => $discount,
            'vat' => $vat,
            'total' => $subtotal - $discount + $vat,
        ];
    }

    /**
     * Take a share of an amount, rounded half up to a whole cent, using integer maths only.
     *
     * @param  int  $amount  The amount in cents.
     * @param  int  $basisPoints  The share in basis points (100 basis points is 1%).
     * @return int The share in cents.
     */
    private function share(int $amount, int $basisPoints): int
    {
        return intdiv($amount * $basisPoints + 5000, 10000);
    }
}
