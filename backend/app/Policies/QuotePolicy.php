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
     */
    public function view(Workshop $workshop, Quote $quote): Response
    {
        return $this->ownedBy($workshop, $quote);
    }

    /**
     * Whether the workshop may change the quote (lines and submit).
     *
     * @param  Workshop  $workshop  The authenticated workshop.
     * @param  Quote  $quote  The quote.
     */
    public function update(Workshop $workshop, Quote $quote): Response
    {
        return $this->ownedBy($workshop, $quote);
    }

    private function ownedBy(Workshop $workshop, Quote $quote): Response
    {
        return $quote->workshop_id === $workshop->id ? Response::allow() : Response::denyAsNotFound();
    }
}
