<?php

namespace App\Services;

use App\Exceptions\QuoteLockedException;
use App\Models\Colour;
use App\Models\Part;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\Workshop;
use App\QuoteStatus;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QuoteService
{
    /**
     * Create the quote service.
     *
     * @param  QuoteCalculator  $calculator  Calculates the totals.
     */
    public function __construct(private QuoteCalculator $calculator) {}

    /**
     * Start a new, empty draft quote for a workshop.
     *
     * @param  Workshop  $workshop  The workshop the quote belongs to.
     * @return Quote The new draft quote.
     */
    public function create(Workshop $workshop): Quote
    {
        return $workshop->quotes()->create(['status' => QuoteStatus::Draft]);
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
     * @throws QuoteLockedException When the quote has been submitted.
     * @throws ValidationException When the colour or quantity is not allowed.
     */
    public function addLine(Quote $quote, Part $part, int $quantity, ?string $colourName): Quote
    {
        $this->assertEditable($quote);
        $colour = $this->resolveColour($part, $colourName);

        DB::transaction(function () use ($quote, $part, $quantity, $colour) {
            $line = $quote->lines()->where('part_id', $part->id)->where('colour_id', $colour?->id)->first();
            $otherLines = $this->quantityOnOtherLines($quote, $part, $line);

            $newQuantity = $quantity + ($line?->quantity ?? 0);
            $this->assertStockAllows($part, $otherLines + $newQuantity);

            $line ??= new QuoteLine(['quote_id' => $quote->id, 'part_id' => $part->id, 'colour_id' => $colour?->id]);
            $line->quantity = $newQuantity;
            $line->backorder = $part->stock_quantity === 0;
            $line->save();
        });

        return $this->fresh($quote);
    }

    /**
     * Change the quantity and/or colour of a line. A colour change that matches another line merges the two.
     *
     * @param  Quote  $quote  The draft quote.
     * @param  QuoteLine  $line  The line to change.
     * @param  int|null  $quantity  The new quantity, or null to keep it.
     * @param  bool  $changeColour  Whether the colour is part of the change.
     * @param  string|null  $colourName  The new colour name when the colour changes.
     * @return Quote The quote with its lines.
     *
     * @throws QuoteLockedException When the quote has been submitted.
     * @throws ValidationException When the colour or quantity is not allowed.
     */
    public function updateLine(
        Quote $quote,
        QuoteLine $line,
        ?int $quantity,
        bool $changeColour,
        ?string $colourName,
    ): Quote {
        $this->assertEditable($quote);
        $part = $line->part;
        $colour = $changeColour ? $this->resolveColour($part, $colourName) : $line->colour;

        DB::transaction(function () use ($quote, $line, $part, $quantity, $colour) {
            $duplicate = $quote->lines()
                ->where('part_id', $part->id)
                ->where('colour_id', $colour?->id)
                ->whereKeyNot($line->id)
                ->first();

            $newQuantity = ($quantity ?? $line->quantity) + ($duplicate?->quantity ?? 0);
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

        return $this->fresh($quote);
    }

    /**
     * Remove a line from a draft quote.
     *
     * @param  Quote  $quote  The draft quote.
     * @param  QuoteLine  $line  The line to remove.
     * @return Quote The quote with its remaining lines.
     *
     * @throws QuoteLockedException When the quote has been submitted.
     */
    public function removeLine(Quote $quote, QuoteLine $line): Quote
    {
        $this->assertEditable($quote);
        $line->delete();

        return $this->fresh($quote);
    }

    /**
     * Submit a draft quote: re-check the stock of every in-stock line, then snapshot the unit prices,
     * store the totals and lock the quote.
     *
     * @param  Quote  $quote  The draft quote.
     * @return Quote The submitted quote.
     *
     * @throws QuoteLockedException When the quote has already been submitted.
     * @throws ValidationException When the quote has no lines or a line exceeds the current stock.
     */
    public function submit(Quote $quote): Quote
    {
        $this->assertEditable($quote);
        $lines = $quote->lines()->with('part')->get();

        if ($lines->isEmpty()) {
            throw ValidationException::withMessages([
                'lines' => 'A quote needs at least one line before it can be submitted.',
            ]);
        }

        $this->assertStockStillAvailable($lines);

        DB::transaction(function () use ($quote, $lines) {
            foreach ($lines as $line) {
                $line->update(['unit_price_cents' => $line->part->price_cents]);
            }

            $totals = $this->calculator->totals(
                $lines->map(fn (QuoteLine $line) => $line->part->price_cents * $line->quantity)->all(),
                $quote->workshop->discount_percentage,
            );

            $quote->update([
                'status' => QuoteStatus::Submitted,
                'submitted_at' => now(),
                'subtotal_cents' => $totals['subtotal'],
                'discount_cents' => $totals['discount'],
                'vat_cents' => $totals['vat'],
                'total_cents' => $totals['total'],
            ]);
        });

        return $this->fresh($quote);
    }

    /**
     * The totals of a quote: the stored ones once submitted, otherwise calculated live from current prices.
     *
     * @param  Quote  $quote  The quote.
     * @return array{subtotal: int, discount: int, vat: int, total: int}
     */
    public function totals(Quote $quote): array
    {
        if ($quote->isSubmitted()) {
            return [
                'subtotal' => $quote->subtotal_cents,
                'discount' => $quote->discount_cents,
                'vat' => $quote->vat_cents,
                'total' => $quote->total_cents,
            ];
        }

        return $this->calculator->totals(
            $quote->lines->map(fn (QuoteLine $line) => $line->part->price_cents * $line->quantity)->all(),
            $quote->workshop->discount_percentage,
        );
    }

    /**
     * @throws QuoteLockedException
     */
    private function assertEditable(Quote $quote): void
    {
        if ($quote->isSubmitted()) {
            throw new QuoteLockedException;
        }
    }

    /**
     * Find the colour for a line: a part with colours needs one of its colours, a part without colours takes none.
     *
     * @throws ValidationException
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
     * The quantity of the part on the other lines of the quote, whatever their colour.
     */
    private function quantityOnOtherLines(Quote $quote, Part $part, ?QuoteLine $except): int
    {
        return (int) $quote->lines()
            ->where('part_id', $part->id)
            ->when($except, fn ($query) => $query->whereKeyNot($except->id))
            ->sum('quantity');
    }

    /**
     * A part in stock cannot be quoted beyond its stock; a part without stock may be backordered.
     *
     * @throws ValidationException
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
     * Re-check, per part, that the lines that were in stock still fit the current stock.
     *
     * @param  Collection<int, QuoteLine>  $lines
     *
     * @throws ValidationException
     */
    private function assertStockStillAvailable(Collection $lines): void
    {
        $errors = [];

        foreach ($lines->where('backorder', false)->groupBy('part_id') as $partLines) {
            $part = $partLines->first()->part;
            $requested = $partLines->sum('quantity');

            if ($requested > $part->stock_quantity) {
                foreach ($partLines as $line) {
                    $errors["lines.{$line->id}"] = "Only {$part->stock_quantity} in stock for {$part->name}; "
                        ."the quote has {$requested}.";
                }
            }
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * Reload the quote with the relations the response needs.
     */
    private function fresh(Quote $quote): Quote
    {
        return $quote->fresh(['workshop', 'lines.part', 'lines.colour']);
    }
}
