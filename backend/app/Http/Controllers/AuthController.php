<?php

namespace App\Http\Controllers;

use App\Http\Resources\WorkshopResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuthController extends Controller
{
    /**
     * Show the workshop that owns the bearer token.
     *
     * @param  Request  $request  The authenticated request.
     * @return WorkshopResource The authenticated workshop.
     */
    public function me(Request $request): JsonResource
    {
        return $request->user()->toResource();
    }
}
