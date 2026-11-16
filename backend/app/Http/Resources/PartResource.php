<?php

namespace App\Http\Resources;

use App\Models\Colour;
use App\Models\Part;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Part
 */
class PartResource extends JsonResource
{
    /**
     * Transform the part into its API representation.
     *
     * @param  Request  $request  The current request.
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'oem' => $this->oem,
            'brand' => $this->brand->name,
            'category' => $this->category->name,
            'condition' => $this->condition->value,
            'priceCents' => $this->price_cents,
            'stockQuantity' => $this->stock_quantity,
            'inStock' => $this->in_stock,
            'image' => $this->image,
            'colours' => $this->colours->map(fn (Colour $colour): string => $colour->name)->all(),
        ];
    }
}
