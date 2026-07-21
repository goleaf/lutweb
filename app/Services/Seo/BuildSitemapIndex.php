<?php

namespace App\Services\Seo;

use App\Models\Category;
use App\Models\Product;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;

class BuildSitemapIndex
{
    public function xml(): string
    {
        $ttl = max(1, (int) config('seo.sitemap.cache_lifetime_seconds', 3600));

        return Cache::remember('seo:sitemap:index', $ttl, fn (): string => $this->buildUrlSet());
    }

    private function buildUrlSet(): string
    {
        $urls = collect([
            $this->entry('/', now()),
            $this->entry('/shop', now()),
            $this->entry('/terms', now()),
            $this->entry('/privacy', now()),
            $this->entry('/terms-of-sale', now()),
            $this->entry('/license', now()),
            $this->entry('/refund-policy', now()),
        ]);

        Product::query()
            ->select(['id', 'slug', 'updated_at', 'published_at', 'status'])
            ->published()
            ->orderBy('id')
            ->limit((int) config('seo.sitemap.max_urls', 50_000))
            ->get()
            ->each(fn (Product $product) => $urls->push($this->entry(
                route('shop.show', $product->slug, absolute: false),
                $product->updated_at ?? $product->published_at ?? now(),
            )));

        Category::query()
            ->select(['id', 'name', 'slug', 'is_active', 'updated_at'])
            ->where('is_active', true)
            ->whereHas('products', fn (Builder $query): Builder => Product::applyPublishedConstraints($query))
            ->orderBy('id')
            ->limit((int) config('seo.sitemap.max_urls', 50_000))
            ->get()
            ->each(fn (Category $category) => $urls->push($this->entry(
                route('categories.show', $category->slug, absolute: false),
                $category->updated_at ?? now(),
            )));

        $body = $urls
            ->unique('loc')
            ->take((int) config('seo.sitemap.max_urls', 50_000))
            ->map(fn (array $entry): string => $this->urlXml($entry))
            ->implode('');

        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'
            .$body
            .'</urlset>';
    }

    /**
     * @return array{loc: string, lastmod: string}
     */
    private function entry(string $path, CarbonInterface $lastModified): array
    {
        return [
            'loc' => $this->absolute($path),
            'lastmod' => $lastModified->toDateString(),
        ];
    }

    /**
     * @param  array{loc: string, lastmod: string}  $entry
     */
    private function urlXml(array $entry): string
    {
        return '<url><loc>'.$this->escape($entry['loc']).'</loc><lastmod>'.$entry['lastmod'].'</lastmod></url>';
    }

    private function absolute(string $path): string
    {
        $base = trim((string) config('seo.canonical_url', ''), '/');

        if ($base !== '') {
            return $base.'/'.ltrim($path, '/');
        }

        return URL::to($path === '/' ? '/' : '/'.ltrim($path, '/'));
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }
}
