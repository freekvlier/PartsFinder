<?php

namespace App\Exceptions;

use RuntimeException;

class QuoteLockedException extends RuntimeException
{
    /**
     * Create the exception for a quote that has already been submitted.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct('This quote has been submitted and can no longer be changed.');
    }
}
