<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BrandResource;
use App\Models\Brand;

class BrandController extends Controller
{
    /**
     * Display the specified resource.
     */
    public function show(Brand $brand): BrandResource
    {
        $brand->loadCount('products');

        return new BrandResource($brand);
    }
}
