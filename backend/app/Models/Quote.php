<?php

namespace App\Models;

use App\QuoteStatus;
use Database\Factories\QuoteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['workshop_id', 'status', 'submitted_at', 'subtotal_cents', 'discount_cents', 'vat_cents', 'total_cents'])]
class Quote extends Model
{
    /** @use HasFactory<QuoteFactory> */
    use HasFactory;

    /**
     * The attribute casts of the model.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => QuoteStatus::class,
            'submitted_at' => 'datetime',
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
     * Whether the quote has been submitted and is read-only.
     */
    public function isSubmitted(): bool
    {
        return $this->status === QuoteStatus::Submitted;
    }
}
