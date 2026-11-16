<?php

namespace App\Http\Resources;

use App\Models\Brand;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Brand
 */
class BrandResource extends JsonResource
{
    /**
     * Transform the brand into its API representation: its name and how many parts match the active search.
     *
     * @param  Request  $request  The current request.
     * @return array{name: string, count: int}
     */
    public function toArray(Request $request): array
    {
        return [
            'name' => $this->name,
            'count' => (int) $this->parts_count,
        ];
    }
}
