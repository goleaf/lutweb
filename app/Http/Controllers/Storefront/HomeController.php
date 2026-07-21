<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Resources\Storefront\CategoryResource;
use App\Http\Resources\Storefront\ProductCardResource;
use App\Queries\Storefront\ProductCatalogQuery;
use App\Services\Seo\SeoMetaFactory;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __invoke(ProductCatalogQuery $catalog, SeoMetaFactory $seo): Response
    {
        $filterOptions = $catalog->filterOptions();

        return Inertia::render('Home', [
            'featuredProducts' => ProductCardResource::collection($catalog->featured()),
            'categories' => CategoryResource::collection($filterOptions['categories']),
            'freeProducts' => ProductCardResource::collection($catalog->free()),
            'seo' => $seo->home()->toArray(),
        ]);
    }
}
