<?php

namespace App\Http\Resources;

use App\Models\Quote;
use App\Models\QuoteLine;
use App\Services\QuoteService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Quote
 */
class QuoteResource extends JsonResource
{
    /**
     * Transform the quote, with its lines, warnings and server-calculated totals, into its API representation.
     *
     * @param  Request  $request  The current request.
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $totals = app(QuoteService::class)->totals($this->resource);

        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'submittedAt' => $this->submitted_at?->toIso8601String(),
            'discountPercentage' => $this->workshop->discount_percentage,
            'lines' => $this->lines->map(fn (QuoteLine $line): array => [
                'id' => $line->id,
                'partId' => $line->part_id,
                'name' => $line->part->name,
                'oem' => $line->part->oem,
                'image' => $line->part->image,
                'colour' => $line->colour?->name,
                'quantity' => $line->quantity,
                'backorder' => $line->backorder,
                'unitPriceCents' => $line->unit_price_cents ?? $line->part->price_cents,
                'lineTotalCents' => ($line->unit_price_cents ?? $line->part->price_cents) * $line->quantity,
            ])->all(),
            /** @var list<array{code: string, message: string, lineIds: list<int>}> */
            'warnings' => $this->warnings(),
            'totals' => [
                'subtotalCents' => (int) $totals['subtotal'],
                'discountCents' => (int) $totals['discount'],
                'vatCents' => (int) $totals['vat'],
                'totalCents' => (int) $totals['total'],
            ],
        ];
    }

    /**
     * The warnings of the quote: one listing the lines that will be backordered, if there are any.
     *
     * @return list<array{code: string, message: string, lineIds: list<int>}>
     */
    private function warnings(): array
    {
        $backorderLineIds = $this->lines->where('backorder', true)->pluck('id')->values()->all();

        if ($backorderLineIds === []) {
            return [];
        }

        return [[
            'code' => 'backorder',
            'message' => 'Some lines are out of stock and will be backordered.',
            'lineIds' => $backorderLineIds,
        ]];
    }
}
