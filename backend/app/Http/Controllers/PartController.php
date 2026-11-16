<?php

namespace App\Http\Controllers;

use App\Http\Requests\IndexPartsRequest;
use App\Http\Resources\PartResource;
use App\Models\Part;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PartController extends Controller
{
    /**
     * List the parts, paginated.
     *
     * @param  IndexPartsRequest  $request  The validated query parameters.
     * @return AnonymousResourceCollection The requested page of parts.
     */
    public function index(IndexPartsRequest $request): AnonymousResourceCollection
    {
        $parts = Part::with(['brand', 'category', 'colours'])
            ->orderBy('name')
            ->orderBy('id')
            ->paginate($request->integer('per_page', 12));

        return PartResource::collection($parts);
    }
}
