<?php

namespace App\Http\Controllers;

use App\Enums\Condition;
use App\Http\Requests\IndexBrandsRequest;
use App\Http\Resources\BrandResource;
use App\Models\Brand;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BrandController extends Controller
{
    /**
     * List the brands with how many parts match the active search and condition.
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
