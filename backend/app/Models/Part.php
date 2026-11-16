<?php

namespace App\Models;

use App\Condition;
use Database\Factories\PartFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['brand_id', 'category_id', 'name', 'oem', 'condition', 'price_cents', 'stock_quantity', 'image'])]
class Part extends Model
{
    /** @use HasFactory<PartFactory> */
    use HasFactory;

    /**
     * The attribute casts of the model.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'condition' => Condition::class,
            'price_cents' => 'integer',
            'stock_quantity' => 'integer',
        ];
    }

    /**
     * The brand of the part.
     *
     * @return BelongsTo<Brand, $this>
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /**
     * The category of the part.
     *
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * The colours the part is available in; empty when the part has none.
     *
     * @return BelongsToMany<Colour, $this>
     */
    public function colours(): BelongsToMany
    {
        return $this->belongsToMany(Colour::class);
    }

    /**
     * Whether any stock is left, derived from the stock quantity.
     *
     * @return Attribute<bool, never>
     */
    protected function inStock(): Attribute
    {
        return Attribute::get(fn () => $this->stock_quantity > 0);
    }
}
