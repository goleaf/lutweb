<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Resources\Storefront\ProductCardResource;
use App\Http\Resources\Storefront\ProductDetailResource;
use App\Queries\Storefront\ProductCatalogQuery;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function show(string $slug, Request $request, ProductCatalogQuery $catalog): Response
    {
        $product = $catalog->findPublishedBySlug($slug);

        return Inertia::render('Shop/Show', [
            'product' => (new ProductDetailResource($product))->resolve($request),
            'relatedProducts' => ProductCardResource::collection($catalog->related($product)),
        ]);
    }
}
