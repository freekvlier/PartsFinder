<?php

namespace App\Http\Controllers;

use App\Http\Requests\AddQuoteLineRequest;
use App\Http\Requests\UpdateQuoteLineRequest;
use App\Http\Resources\QuoteResource;
use App\Models\Part;
use App\Services\QuoteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
     * @param  int  $quote  The id of the workshop's own draft quote.
     * @return JsonResponse The updated quote, with status 201.
     */
    public function store(AddQuoteLineRequest $request, int $quote): JsonResponse
    {
        $quote = $request->user()->quotes()->draft()->findOrFail($quote);
        $part = Part::with('colours')->findOrFail($request->integer('part_id'));

        $quote = $this->quoteService->addLine($quote, $part, $request->integer('quantity'), $request->input('colour'));

        return $quote->toResource()->response()->setStatusCode(201);
    }

    /**
     * Change the quantity or colour of a line.
     *
     * @param  UpdateQuoteLineRequest  $request  The validated change.
     * @param  int  $quote  The id of the workshop's own draft quote.
     * @param  int  $line  The id of a line of that quote.
     * @return QuoteResource The updated quote.
     */
    public function update(UpdateQuoteLineRequest $request, int $quote, int $line): JsonResource
    {
        $quote = $request->user()->quotes()->draft()->findOrFail($quote);
        $line = $quote->lines()->findOrFail($line);
        $line->load('part.colours', 'colour');

        return $this->quoteService->updateLine($quote, $line, $request->validated())->toResource();
    }

    /**
     * Remove a line from the quote.
     *
     * @param  Request  $request  The authenticated request.
     * @param  int  $quote  The id of the workshop's own draft quote.
     * @param  int  $line  The id of a line of that quote.
     * @return QuoteResource The updated quote.
     */
    public function destroy(Request $request, int $quote, int $line): JsonResource
    {
        $quote = $request->user()->quotes()->draft()->findOrFail($quote);
        $line = $quote->lines()->findOrFail($line);

        return $this->quoteService->removeLine($quote, $line)->toResource();
    }
}
