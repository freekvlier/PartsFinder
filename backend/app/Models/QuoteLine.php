<?php

namespace App\Models;

use Database\Factories\QuoteLineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['quote_id', 'part_id', 'colour_id', 'quantity', 'backorder', 'unit_price_cents'])]
class QuoteLine extends Model
{
    /** @use HasFactory<QuoteLineFactory> */
    use HasFactory;

    /**
     * The attribute casts of the model.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'backorder' => 'boolean',
            'unit_price_cents' => 'integer',
        ];
    }

    /**
     * The quote the line belongs to.
     *
     * @return BelongsTo<Quote, $this>
     */
    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    /**
     * The part that is quoted.
     *
     * @return BelongsTo<Part, $this>
     */
    public function part(): BelongsTo
    {
        return $this->belongsTo(Part::class);
    }

    /**
     * The chosen colour, or null for a part without colours.
     *
     * @return BelongsTo<Colour, $this>
     */
    public function colour(): BelongsTo
    {
        return $this->belongsTo(Colour::class);
    }

    /**
     * The unit price of the line: the snapshot taken on submit, otherwise the current price of the part.
     *
     * @return int The unit price in cents.
     */
    public function unitPriceCents(): int
    {
        return $this->unit_price_cents ?? $this->part->price_cents;
    }

    /**
     * The total of the line: the unit price times the quantity.
     *
     * @return int The line total in cents.
     */
    public function totalCents(): int
    {
        return $this->unitPriceCents() * $this->quantity;
    }
}
