<?php

namespace App\Models;

use Database\Factories\ColourFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name'])]
class Colour extends Model
{
    /** @use HasFactory<ColourFactory> */
    use HasFactory;
}
