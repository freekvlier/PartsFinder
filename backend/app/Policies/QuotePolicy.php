<?php

namespace App\Policies;

use App\Models\Quote;
use App\Models\Workshop;
use Illuminate\Auth\Access\Response;

class QuotePolicy
{
    /**
     * Whether the workshop may see the quote. Another workshop's quote answers as if it did not exist (404).
     *
     * @param  Workshop  $workshop  The authenticated workshop.
     * @param  Quote  $quote  The quote.
     * @return Response Allowed for the owner, otherwise a 404.
     */
    public function view(Workshop $workshop, Quote $quote): Response
    {
        return $quote->isOwnedBy($workshop) ? Response::allow() : Response::denyAsNotFound();
    }

    /**
     * Whether the workshop may change the quote (lines and submit): only its own draft quotes.
     *
     * @param  Workshop  $workshop  The authenticated workshop.
     * @param  Quote  $quote  The quote.
     * @return Response Allowed for the owner of a draft; a 404 for another workshop; a 409 once submitted.
     */
    public function update(Workshop $workshop, Quote $quote): Response
    {
        if (! $quote->isOwnedBy($workshop)) {
            return Response::denyAsNotFound();
        }

        return $quote->isSubmitted()
            ? Response::deny('This quote has been submitted and can no longer be changed.')->withStatus(409)
            : Response::allow();
    }
}
