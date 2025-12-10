<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CollectionController extends Controller
{
    /**
     * Get products for a collection by slug.
     */
    public function products(Collection $collection, Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = $collection->products()
            ->with('category', 'brand', 'fieldValues.productField');

        if (isset($validated['limit'])) {
            $query->limit($validated['limit']);
        }

        $products = $query->get();

        return ProductResource::collection($products);
    }
}
