<?php

namespace App\Models;

use App\Enums\QuoteStatus;
use App\Services\QuoteCalculator;
use Database\Factories\QuoteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'workshop_id',
    'status',
    'discount_percentage',
    'vat_percentage',
    'submitted_at',
    'subtotal_cents',
    'discount_cents',
    'vat_cents',
    'total_cents',
])]
class Quote extends Model
{
    /** @use HasFactory<QuoteFactory> */
    use HasFactory;

    /** The relations `QuoteResource` reads; load them before transforming a quote. */
    public const RESOURCE_RELATIONS = ['workshop', 'lines.part', 'lines.colour'];

    /**
     * The attribute casts of the model.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => QuoteStatus::class,
            'discount_percentage' => 'float',
            'vat_percentage' => 'float',
            'submitted_at' => 'datetime',
            'subtotal_cents' => 'integer',
            'discount_cents' => 'integer',
            'vat_cents' => 'integer',
            'total_cents' => 'integer',
        ];
    }

    /**
     * The workshop the quote belongs to.
     *
     * @return BelongsTo<Workshop, $this>
     */
    public function workshop(): BelongsTo
    {
        return $this->belongsTo(Workshop::class);
    }

    /**
     * The lines of the quote, in the order they were added.
     *
     * @return HasMany<QuoteLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(QuoteLine::class)->orderBy('id');
    }

    /**
     * Quotes that can still be changed: the drafts.
     *
     * @param  Builder<Quote>  $query  The quotes query.
     */
    #[Scope]
    protected function draft(Builder $query): void
    {
        $query->where('status', QuoteStatus::Draft);
    }

    /**
     * Whether the quote has been submitted and is read-only.
     *
     * @return bool True once submitted.
     */
    public function isSubmitted(): bool
    {
        return $this->status === QuoteStatus::Submitted;
    }

    /**
     * The totals: stored once submitted, otherwise calculated live from the line totals and the quote's own rates.
     *
     * @return array{subtotal: int, discount: int, vat: int, total: int}
     */
    public function totals(): array
    {
        if ($this->isSubmitted()) {
            return [
                'subtotal' => $this->subtotal_cents,
                'discount' => $this->discount_cents,
                'vat' => $this->vat_cents,
                'total' => $this->total_cents,
            ];
        }

        return QuoteCalculator::totals(
            $this->lines->map(fn (QuoteLine $line) => $line->totalCents())->all(),
            $this->discount_percentage,
            $this->vat_percentage,
        );
    }
}
