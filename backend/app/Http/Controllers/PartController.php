<?php

namespace App\Http\Controllers;

use App\Enums\Condition;
use App\Http\Requests\IndexPartsRequest;
use App\Http\Resources\PartResource;
use App\Models\Part;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PartController extends Controller
{
    /**
     * List the parts that match the search, brand and condition filters, sorted and paginated.
     *
     * @param  IndexPartsRequest  $request  The validated query parameters.
     * @return AnonymousResourceCollection The requested page of parts.
     */
    public function index(IndexPartsRequest $request): AnonymousResourceCollection
    {
        $query = Part::with(['brand', 'category', 'colours']);

        $query->search($request->input('q'))
            ->ofBrands($request->array('brand'))
            ->inCondition($request->enum('condition', Condition::class))
            ->sortedBy($request->input('sort'));

        $parts = $query->paginate($request->integer('per_page', 12));

        return PartResource::collection($parts);
    }

    /**
     * Show one part.
     *
     * @param  Part  $part  The part.
     * @return PartResource The part with its brand, category and colours.
     */
    public function show(Part $part): PartResource
    {
        return new PartResource($part->load(['brand', 'category', 'colours']));
    }
}
