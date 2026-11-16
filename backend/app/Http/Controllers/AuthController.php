<?php

namespace App\Http\Controllers;

use App\Models\Workshop;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    /**
     * Show the workshop that owns the bearer token.
     *
     * @param  Request  $request  The authenticated request.
     * @return Workshop The authenticated workshop.
     */
    public function me(Request $request): Workshop
    {
        return $request->user();
    }
}
