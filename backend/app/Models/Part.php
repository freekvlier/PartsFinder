<?php

namespace App\Models;

use App\Enums\Condition;
use Database\Factories\PartFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
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
     * Parts whose name or OEM number contains the term. A blank term matches all.
     *
     * @param  Builder<Part>  $query  The parts query.
     * @param  string|null  $term  The text to look for.
     */
    #[Scope]
    protected function search(Builder $query, ?string $term): void
    {
        $term = trim((string) $term);

        if ($term === '') {
            return;
        }

        $query->where(fn (Builder $query) => $query
            ->whereLike('name', "%{$term}%")
            ->orWhereLike('oem', "%{$term}%"));
    }

    /**
     * Parts in the given condition. No condition matches everything.
     *
     * @param  Builder<Part>  $query  The parts query.
     * @param  Condition|null  $condition  The condition to keep.
     */
    #[Scope]
    protected function inCondition(Builder $query, ?Condition $condition): void
    {
        $query->when($condition, fn (Builder $query) => $query->where('condition', $condition));
    }

    /**
     * Parts of any of the given brands. No brands matches everything.
     *
     * @param  Builder<Part>  $query  The parts query.
     * @param  list<string>  $brandNames  The brand names to keep.
     */
    #[Scope]
    protected function ofBrands(Builder $query, array $brandNames): void
    {
        $query->when($brandNames !== [], fn (Builder $query) => $query->whereIn(
            'brand_id',
            Brand::query()->whereIn('name', $brandNames)->select('id'),
        ));
    }

    /**
     * Order the parts by price when asked, otherwise by name. Name and id break ties so pages stay stable.
     *
     * @param  Builder<Part>  $query  The parts query.
     * @param  string|null  $sort  `price_asc` or `price_desc`.
     */
    #[Scope]
    protected function sortedBy(Builder $query, ?string $sort): void
    {
        match ($sort) {
            'price_asc' => $query->orderBy('price_cents'),
            'price_desc' => $query->orderByDesc('price_cents'),
            default => null,
        };

        $query->orderBy('name')->orderBy('id');
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
