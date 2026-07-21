<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Resources\Storefront\CategoryResource;
use App\Http\Resources\Storefront\ProductCardResource;
use App\Queries\Storefront\ProductCatalogQuery;
use App\Services\Seo\SeoMetaFactory;
use App\Support\Storefront\StorefrontFilterData;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ShopController extends Controller
{
    public function index(Request $request, ProductCatalogQuery $catalog, SeoMetaFactory $seo): Response
    {
        $filters = StorefrontFilterData::fromRequest($request);
        $products = $catalog->paginate($filters);

        return Inertia::render('Shop/Index', [
            'products' => ProductCardResource::collection($products),
            'resultCount' => $products->total(),
            'filters' => $filters->toArray(),
            'filterOptions' => $this->filterOptions($catalog),
            'seo' => $seo->shop(filtered: $filters->isFiltered())->toArray(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function filterOptions(ProductCatalogQuery $catalog): array
    {
        $options = $catalog->filterOptions();

        return [
            'categories' => CategoryResource::collection($options['categories']),
            'tags' => $options['tags']
                ->map(fn ($tag): array => [
                    'id' => $tag->id,
                    'name' => $tag->name,
                    'slug' => $tag->slug,
                    'products_count' => (int) ($tag->published_products_count ?? 0),
                ])
                ->values(),
            'software' => $options['software']
                ->map(fn ($software): array => [
                    'id' => $software->id,
                    'name' => $software->name,
                    'slug' => $software->slug,
                    'website_url' => $software->website_url,
                    'products_count' => (int) ($software->published_products_count ?? 0),
                ])
                ->values(),
        ];
    }
}
