<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Resources\Storefront\CategoryResource;
use App\Http\Resources\Storefront\ProductCardResource;
use App\Queries\Storefront\ProductCatalogQuery;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __invoke(ProductCatalogQuery $catalog): Response
    {
        $filterOptions = $catalog->filterOptions();

        return Inertia::render('Home', [
            'featuredProducts' => ProductCardResource::collection($catalog->featured()),
            'categories' => CategoryResource::collection($filterOptions['categories']),
            'freeProducts' => ProductCardResource::collection($catalog->free()),
            'seo' => [
                'title' => 'LUT Web - Professional LUTs for photographers and creators',
                'description' => 'Try looks on your photos, create custom LUTs, and securely download your purchases.',
                'canonical_url' => route('home'),
            ],
        ]);
    }
}
