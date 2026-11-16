<?php

namespace App\Http\Resources;

use App\Models\Workshop;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Workshop
 */
class WorkshopResource extends JsonResource
{
    /**
     * Transform the workshop into its API representation.
     *
     * @param  Request  $request  The current request.
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'discount_percentage' => $this->discount_percentage,
        ];
    }
}
