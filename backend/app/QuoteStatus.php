<?php

namespace App;

enum QuoteStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
}
