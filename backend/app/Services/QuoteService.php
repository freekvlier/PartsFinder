<?php

namespace App\Services;

use App\Enums\QuoteStatus;
use App\Models\Colour;
use App\Models\Part;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\Workshop;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QuoteService
{
    /**
     * Start a new, empty draft quote for a workshop. The quote keeps the discount and VAT rates of that moment.
     *
     * @param  Workshop  $workshop  The workshop the quote belongs to.
     * @return Quote The new draft quote.
     */
    public function create(Workshop $workshop): Quote
    {
        $quote = $workshop->quotes()->create([
            'status' => QuoteStatus::Draft,
            'discount_percentage' => $workshop->discount_percentage,
            'vat_percentage' => config('quotes.vat_percentage'),
        ]);

        return $this->reloadWithRelations($quote);
    }

    /**
     * Add a part to a draft quote. Adding the same part in the same colour increases that line's quantity.
     *
     * @param  Quote  $quote  The draft quote.
     * @param  Part  $part  The part to add.
     * @param  int  $quantity  The quantity to add.
     * @param  string|null  $colourName  The colour name, required when the part has colours.
     * @return Quote The quote with its lines.
     *
     * @throws ValidationException When the colour or quantity is not allowed.
     */
    public function addLine(Quote $quote, Part $part, int $quantity, ?string $colourName): Quote
    {
        $colour = $this->resolveColour($part, $colourName);

        DB::transaction(function () use ($quote, $part, $quantity, $colour) {
            $line = $quote->lines()->where('part_id', $part->id)->where('colour_id', $colour?->id)->first();
            $otherLines = $quote->lines()
                ->where('part_id', $part->id)
                ->when($line, fn ($query) => $query->whereKeyNot($line->id))
                ->sum('quantity');

            $newQuantity = $quantity + ($line?->quantity ?? 0);
            $this->assertStockAllows($part, $otherLines + $newQuantity);

            $line ??= new QuoteLine(['quote_id' => $quote->id, 'part_id' => $part->id, 'colour_id' => $colour?->id]);
            $line->quantity = $newQuantity;
            $line->backorder = $part->stock_quantity === 0;
            $line->save();
        });

        return $this->reloadWithRelations($quote);
    }

    /**
     * Change the quantity and/or colour of a line. A colour change that matches another line merges the two.
     *
     * @param  Quote  $quote  The draft quote.
     * @param  QuoteLine  $line  The line to change.
     * @param  array{quantity?: int, colour?: string|null}  $changes  The changes; an absent key keeps the value.
     * @return Quote The quote with its lines.
     *
     * @throws ValidationException When the colour or quantity is not allowed.
     */
    public function updateLine(Quote $quote, QuoteLine $line, array $changes): Quote
    {
        $part = $line->part;
        $colour = array_key_exists('colour', $changes)
            ? $this->resolveColour($part, $changes['colour'])
            : $line->colour;
        $quantity = isset($changes['quantity']) ? (int) $changes['quantity'] : $line->quantity;

        DB::transaction(function () use ($quote, $line, $part, $quantity, $colour) {
            $duplicate = $quote->lines()
                ->where('part_id', $part->id)
                ->where('colour_id', $colour?->id)
                ->whereKeyNot($line->id)
                ->first();

            $newQuantity = $quantity + ($duplicate?->quantity ?? 0);
            $otherLines = $quote->lines()
                ->where('part_id', $part->id)
                ->whereKeyNot($line->id)
                ->when($duplicate, fn ($query) => $query->whereKeyNot($duplicate->id))
                ->sum('quantity');
            $this->assertStockAllows($part, $otherLines + $newQuantity);

            $duplicate?->delete();
            $line->update([
                'colour_id' => $colour?->id,
                'quantity' => $newQuantity,
                'backorder' => $part->stock_quantity === 0,
            ]);
        });

        return $this->reloadWithRelations($quote);
    }

    /**
     * Remove a line from a draft quote.
     *
     * @param  Quote  $quote  The draft quote.
     * @param  QuoteLine  $line  The line to remove.
     * @return Quote The quote with its remaining lines.
     */
    public function removeLine(Quote $quote, QuoteLine $line): Quote
    {
        $line->delete();

        return $this->reloadWithRelations($quote);
    }

    /**
     * Submit a draft quote: check the stock, snapshot the unit prices, store the totals and lock it.
     *
     * @param  Quote  $quote  The draft quote.
     * @return Quote The submitted quote.
     *
     * @throws ValidationException When the quote has no lines or a line exceeds the current stock.
     */
    public function submit(Quote $quote): Quote
    {
        $lines = $quote->lines()->with('part')->get();

        if ($lines->isEmpty()) {
            throw ValidationException::withMessages([
                'lines' => 'A quote needs at least one line before it can be submitted.',
            ]);
        }

        $errors = [];

        foreach ($lines->where('backorder', false)->groupBy('part_id') as $partLines) {
            $part = $partLines->first()->part;
            $requested = $partLines->sum('quantity');

            if ($requested > $part->stock_quantity) {
                foreach ($partLines as $partLine) {
                    $errors["lines.{$partLine->id}"] = "Only {$part->stock_quantity} in stock for {$part->name}; "
                        ."the quote has {$requested}.";
                }
            }
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        DB::transaction(function () use ($quote, $lines) {
            foreach ($lines as $line) {
                $line->update(['unit_price_cents' => $line->part->price_cents]);
            }

            $totals = $quote->setRelation('lines', $lines)->totals();

            $quote->update([
                'status' => QuoteStatus::Submitted,
                'submitted_at' => now(),
                'subtotal_cents' => $totals['subtotal'],
                'discount_cents' => $totals['discount'],
                'vat_cents' => $totals['vat'],
                'total_cents' => $totals['total'],
            ]);
        });

        return $this->reloadWithRelations($quote);
    }

    /**
     * Find the colour for a line: a part with colours needs one of its colours, a part without colours takes none.
     *
     * @param  Part  $part  The part, with its colours loaded.
     * @param  string|null  $colourName  The requested colour name, if any.
     * @return Colour|null The colour, or null for a part without colours.
     *
     * @throws ValidationException When the colour is missing, unknown, or given for a part without colours.
     */
    private function resolveColour(Part $part, ?string $colourName): ?Colour
    {
        $available = $part->colours;

        if ($available->isEmpty()) {
            if (filled($colourName)) {
                throw ValidationException::withMessages(['colour' => 'This part is not available in colours.']);
            }

            return null;
        }

        $colour = $available->firstWhere('name', $colourName);

        if (! $colour) {
            throw ValidationException::withMessages([
                'colour' => 'Choose one of the available colours: '.$available->pluck('name')->implode(', ').'.',
            ]);
        }

        return $colour;
    }

    /**
     * A part in stock cannot be quoted beyond its stock; a part without stock may be backordered.
     *
     * @param  Part  $part  The part.
     * @param  int  $totalQuantity  The quantity of the part on the whole quote, in any colour.
     *
     * @throws ValidationException When the quantity exceeds the stock of a part that is in stock.
     */
    private function assertStockAllows(Part $part, int $totalQuantity): void
    {
        $stock = $part->stock_quantity;

        if ($stock > 0 && $totalQuantity > $stock) {
            throw ValidationException::withMessages([
                'quantity' => "Only {$stock} in stock for {$part->name}; this quote would hold {$totalQuantity}.",
            ]);
        }
    }

    /**
     * Reload the quote with the relations the response needs.
     *
     * @param  Quote  $quote  The quote.
     * @return Quote The reloaded quote.
     */
    private function reloadWithRelations(Quote $quote): Quote
    {
        return $quote->fresh(Quote::RESOURCE_RELATIONS);
    }
}
