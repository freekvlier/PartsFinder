<?php

namespace App\Http\Resources;

use App\Models\Quote;
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
        $totals = $this->totals();

        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'discount_percentage' => $this->discount_percentage,
            'vat_percentage' => $this->vat_percentage,
            'lines' => QuoteLineResource::collection($this->lines),
            /** @var list<array{code: string, message: string, line_ids: list<int>}> */
            'warnings' => $this->warnings(),
            'totals' => [
                'subtotal_cents' => $totals['subtotal'],
                'discount_cents' => $totals['discount'],
                'vat_cents' => $totals['vat'],
                'total_cents' => $totals['total'],
            ],
        ];
    }

    /**
     * The warnings of the quote: one listing the lines that will be backordered, if there are any.
     *
     * @return list<array{code: string, message: string, line_ids: list<int>}>
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
            'line_ids' => $backorderLineIds,
        ]];
    }
}
