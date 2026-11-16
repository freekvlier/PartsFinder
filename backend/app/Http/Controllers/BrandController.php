<?php

namespace App\Http\Controllers;

use App\Condition;
use App\Http\Requests\IndexBrandsRequest;
use App\Http\Resources\BrandResource;
use App\Models\Brand;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BrandController extends Controller
{
    /**
     * List every brand with the number of parts that match the active search and condition.
     * The brand filter itself is not applied, so a brand's count stays visible while it is selected.
     *
     * @param  IndexBrandsRequest  $request  The validated query parameters.
     * @return AnonymousResourceCollection The brands by name, with their part counts.
     */
    public function index(IndexBrandsRequest $request): AnonymousResourceCollection
    {
        $brands = Brand::query()
            ->withCount(['parts' => fn ($query) => $query
                ->search($request->input('q'))
                ->inCondition($request->enum('condition', Condition::class))])
            ->orderBy('name')
            ->get();

        return BrandResource::collection($brands);
    }
}
