<?php

namespace App\Http\Controllers;

use App\Http\Resources\QuoteResource;
use App\Models\Quote;
use App\Services\QuoteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QuoteController extends Controller
{
    /**
     * Create the controller.
     *
     * @param  QuoteService  $quotes  The quote rules.
     */
    public function __construct(private QuoteService $quotes) {}

    /**
     * Start a new draft quote for the authenticated workshop.
     *
     * @param  Request  $request  The authenticated request.
     * @return JsonResponse The empty quote, with status 201.
     */
    public function store(Request $request): JsonResponse
    {
        $quote = $this->quotes->create($request->user())->load(['workshop', 'lines.part', 'lines.colour']);

        return (new QuoteResource($quote))->response()->setStatusCode(201);
    }

    /**
     * Show one of the workshop's quotes.
     *
     * @param  Quote  $quote  The quote, resolved only among the workshop's own quotes.
     * @return QuoteResource The quote with lines, warnings and totals.
     */
    public function show(Quote $quote): QuoteResource
    {
        return new QuoteResource($quote->load(['workshop', 'lines.part', 'lines.colour']));
    }

    /**
     * Submit a draft quote.
     *
     * @param  Quote  $quote  The draft quote.
     * @return QuoteResource The submitted, now read-only quote.
     */
    public function submit(Quote $quote): QuoteResource
    {
        return new QuoteResource($this->quotes->submit($quote));
    }
}
