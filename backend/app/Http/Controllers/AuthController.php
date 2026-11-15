<?php

namespace App\Http\Controllers;

use App\Models\Workshop;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function me(Request $request): Workshop
    {
        return $request->user();
    }
}
