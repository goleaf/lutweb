<?php

namespace App\Queries\Storefront;

use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Models\Category;
use App\Models\CompatibleSoftware;
use App\Models\Product;
use App\Models\Tag;
use App\Support\Storefront\StorefrontFilterData;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class ProductCatalogQuery
{
    /**
     * @return LengthAwarePaginator<int, Product>
     */
    public function paginate(StorefrontFilterData $filters, int $perPage = 12): LengthAwarePaginator
    {
        return $this->applySort($this->applyFilters($this->cardQuery(), $filters), $filters)
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @return Collection<int, Product>
     */
    public function featured(int $limit = 4): Collection
    {
        return $this->cardQuery()
            ->where('is_featured', true)
            ->latest('published_at')
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    /**
     * @return Collection<int, Product>
     */
    public function free(int $limit = 3): Collection
    {
        return $this->cardQuery()
            ->where('type', ProductType::FreeLut)
            ->latest('published_at')
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    public function findPublishedBySlug(string $slug): Product
    {
        return $this->detailQuery()
            ->where('slug', $slug)
            ->firstOrFail();
    }

    public function findActiveCategory(string $slug): Category
    {
        return Category::query()
            ->select(['id', 'name', 'slug', 'description', 'is_active', 'sort_order'])
            ->where('slug', $slug)
            ->where('is_active', true)
            ->withCount(['products as published_products_count' => $this->publishedCountQuery()])
            ->firstOrFail();
    }

    /**
     * @return Collection<int, Product>
     */
    public function related(Product $product, int $limit = 4): Collection
    {
        $categoryIds = $product->categories->pluck('id');

        if ($categoryIds->isEmpty()) {
            return new Collection;
        }

        return $this->cardQuery()
            ->whereKeyNot($product->id)
            ->whereHas('categories', fn (Builder $query): Builder => $query->whereIn('categories.id', $categoryIds))
            ->limit($limit)
            ->get();
    }

    /**
     * @return array{categories: Collection<int, Category>, tags: Collection<int, Tag>, software: Collection<int, CompatibleSoftware>}
     */
    public function filterOptions(): array
    {
        return [
            'categories' => Category::query()
                ->select(['id', 'name', 'slug', 'description', 'is_active', 'sort_order'])
                ->where('is_active', true)
                ->withCount(['products as published_products_count' => $this->publishedCountQuery()])
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
            'tags' => Tag::query()
                ->select(['id', 'name', 'slug'])
                ->whereHas('products', $this->publishedCountQuery())
                ->withCount(['products as published_products_count' => $this->publishedCountQuery()])
                ->orderBy('name')
                ->get(),
            'software' => CompatibleSoftware::query()
                ->select(['id', 'name', 'slug', 'website_url', 'is_active', 'sort_order'])
                ->where('is_active', true)
                ->whereHas('products', $this->publishedCountQuery())
                ->withCount(['products as published_products_count' => $this->publishedCountQuery()])
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
        ];
    }

    /**
     * @return Builder<Product>
     */
    private function cardQuery(): Builder
    {
        return Product::query()
            ->select([
                'id',
                'type',
                'status',
                'name',
                'slug',
                'short_description',
                'price_cents',
                'currency',
                'is_featured',
                'published_at',
            ])
            ->published()
            ->with([
                'coverMedia:id,product_id,kind,path,alt_text,width,height,sort_order',
                'categories:id,name,slug,description,is_active,sort_order',
            ]);
    }

    /**
     * @return Builder<Product>
     */
    private function detailQuery(): Builder
    {
        return Product::query()
            ->published()
            ->with([
                'coverMedia:id,product_id,kind,path,alt_text,width,height,sort_order',
                'galleryMedia:id,product_id,kind,path,alt_text,width,height,sort_order',
                'activeExamples:id,product_id,title,before_path,before_alt_text,after_path,after_alt_text,sort_order,is_active',
                'categories:id,name,slug,description,is_active,sort_order',
                'tags:id,name,slug',
                'compatibleSoftware' => fn ($query) => $query
                    ->select(['compatible_software.id', 'name', 'slug', 'website_url', 'is_active', 'sort_order'])
                    ->where('is_active', true)
                    ->orderBy('sort_order')
                    ->orderBy('name'),
                'currentVersion:id,product_id,version,status,is_current,released_at',
                'currentVersion.files:id,product_version_id,kind,disk,path',
                'bundleItems:id,bundle_id,product_id,sort_order',
                'bundleItems.product:id,type,status,name,slug,short_description,price_cents,currency,is_featured,published_at,deleted_at',
                'bundleItems.product.coverMedia:id,product_id,kind,path,alt_text,width,height,sort_order',
            ]);
    }

    /**
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    private function applyFilters(Builder $query, StorefrontFilterData $filters): Builder
    {
        if ($filters->q !== null) {
            $search = $filters->q;

            $query->where(function (Builder $query) use ($search): void {
                $query
                    ->where('name', 'like', '%'.$search.'%')
                    ->orWhere('short_description', 'like', '%'.$search.'%')
                    ->orWhereHas('categories', fn (Builder $query): Builder => $query->where('name', 'like', '%'.$search.'%'))
                    ->orWhereHas('tags', fn (Builder $query): Builder => $query->where('name', 'like', '%'.$search.'%'));
            });
        }

        if ($filters->category !== null) {
            $query->whereHas('categories', fn (Builder $query): Builder => $query
                ->where('slug', $filters->category)
                ->where('is_active', true));
        }

        if ($filters->tag !== null) {
            $query->whereHas('tags', fn (Builder $query): Builder => $query->where('slug', $filters->tag));
        }

        if ($filters->software !== null) {
            $query->whereHas('compatibleSoftware', fn (Builder $query): Builder => $query
                ->where('slug', $filters->software)
                ->where('is_active', true));
        }

        if ($filters->type !== 'all') {
            $query->where('type', $filters->type);
        }

        if ($filters->pricing === 'free') {
            $query->where('price_cents', 0);
        }

        if ($filters->pricing === 'paid') {
            $query->where('price_cents', '>', 0);
        }

        return $query;
    }

    /**
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    private function applySort(Builder $query, StorefrontFilterData $filters): Builder
    {
        return match ($filters->sort) {
            'newest' => $query->latest('published_at')->latest('id'),
            'price_asc' => $query->orderBy('price_cents')->latest('id'),
            'price_desc' => $query->orderByDesc('price_cents')->latest('id'),
            'name_asc' => $query->orderBy('name')->latest('id'),
            default => $query->orderByDesc('is_featured')->latest('published_at')->latest('id'),
        };
    }

    private function publishedCountQuery(): Closure
    {
        return function (Builder $query): void {
            $query
                ->where('status', ProductStatus::Published)
                ->whereNotNull('published_at')
                ->where('published_at', '<=', now());
        };
    }
}
