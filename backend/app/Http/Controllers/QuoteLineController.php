<?php

namespace App\Http\Controllers;

use App\Http\Requests\AddQuoteLineRequest;
use App\Http\Requests\UpdateQuoteLineRequest;
use App\Http\Resources\QuoteResource;
use App\Models\Part;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Services\QuoteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;

class QuoteLineController extends Controller
{
    /**
     * Create the controller.
     *
     * @param  QuoteService  $quoteService  The quote rules.
     */
    public function __construct(private QuoteService $quoteService) {}

    /**
     * Add a part to the quote.
     *
     * @param  AddQuoteLineRequest  $request  The validated line.
     * @param  Quote  $quote  The draft quote.
     * @return JsonResponse The updated quote, with status 201.
     */
    public function store(AddQuoteLineRequest $request, Quote $quote): JsonResponse
    {
        $part = Part::with('colours')->findOrFail($request->integer('part_id'));

        $quote = $this->quoteService->addLine($quote, $part, $request->integer('quantity'), $request->input('colour'));

        return $quote->toResource()->response()->setStatusCode(201);
    }

    /**
     * Change the quantity or colour of a line.
     *
     * @param  UpdateQuoteLineRequest  $request  The validated change.
     * @param  Quote  $quote  The draft quote.
     * @param  QuoteLine  $line  The line, resolved only among this quote's lines.
     * @return QuoteResource The updated quote.
     */
    public function update(UpdateQuoteLineRequest $request, Quote $quote, QuoteLine $line): JsonResource
    {
        $line->load('part.colours', 'colour');

        return $this->quoteService->updateLine($quote, $line, $request->validated())->toResource();
    }

    /**
     * Remove a line from the quote.
     *
     * @param  Quote  $quote  The draft quote.
     * @param  QuoteLine  $line  The line, resolved only among this quote's lines.
     * @return QuoteResource The updated quote.
     */
    public function destroy(Quote $quote, QuoteLine $line): JsonResource
    {
        return $this->quoteService->removeLine($quote, $line)->toResource();
    }
}
