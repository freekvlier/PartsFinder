<?php

namespace App\Http\Resources;

use App\Models\QuoteLine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin QuoteLine
 */
class QuoteLineResource extends JsonResource
{
    /**
     * Transform the line into its API representation: the quoted part, its colour, quantity and prices.
     *
     * @param  Request  $request  The current request.
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'part_id' => $this->part_id,
            'name' => $this->part->name,
            'oem' => $this->part->oem,
            'image' => $this->part->image,
            'colour' => $this->colour?->name,
            'quantity' => $this->quantity,
            'backorder' => $this->backorder,
            'unit_price_cents' => $this->unitPriceCents(),
            'line_total_cents' => $this->totalCents(),
        ];
    }
}
