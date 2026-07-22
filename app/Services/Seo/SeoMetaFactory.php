<?php

namespace App\Services\Seo;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class SeoMetaFactory
{
    public function home(): SeoData
    {
        return $this->make(
            title: (string) config('seo.default_title'),
            description: (string) config('seo.default_description'),
            canonicalPath: '/',
            robots: $this->publicRobots(),
            jsonLd: [
                '@context' => 'https://schema.org',
                '@type' => 'WebSite',
                'name' => (string) config('seo.site_name', 'LUT Web'),
                'url' => $this->absolute('/'),
            ],
        );
    }

    public function shop(bool $filtered = false, ?string $canonicalPath = '/shop'): SeoData
    {
        return $this->make(
            title: 'Shop professional LUTs',
            description: (string) config('seo.default_description'),
            canonicalPath: $canonicalPath,
            robots: $filtered ? 'noindex,follow' : $this->publicRobots(),
        );
    }

    public function category(Category $category): SeoData
    {
        return $this->make(
            title: $category->name.' LUTs',
            description: $category->description ?: (string) config('seo.default_description'),
            canonicalPath: route('categories.show', $category->slug, absolute: false),
            robots: $this->publicRobots(),
        );
    }

    public function product(Product $product, ?string $image = null): SeoData
    {
        $title = $product->meta_title ?: $product->name;
        $description = $product->meta_description ?: $product->short_description;

        return $this->make(
            title: $title,
            description: $description,
            canonicalPath: route('shop.show', $product->slug, absolute: false),
            robots: $product->isPublished() ? $this->publicRobots() : 'noindex,nofollow',
            ogType: 'product',
            ogImage: $image,
            jsonLd: $product->isPublished() ? $this->productJsonLd($product, $image) : null,
        );
    }

    /**
     * @param  list<array{question: string, answer: string}>  $questions
     */
    public function faq(array $questions): SeoData
    {
        return $this->make(
            title: 'LUT questions and answers',
            description: 'Answers about choosing, installing, testing, purchasing, downloading, licensing, and troubleshooting LUTs from LUT Web.',
            canonicalPath: route('faq', absolute: false),
            robots: $this->publicRobots(),
            jsonLd: [
                '@context' => 'https://schema.org',
                '@type' => 'FAQPage',
                'mainEntity' => array_map(static fn (array $question): array => [
                    '@type' => 'Question',
                    'name' => $question['question'],
                    'acceptedAnswer' => [
                        '@type' => 'Answer',
                        'text' => $question['answer'],
                    ],
                ], $questions),
            ],
        );
    }

    public function privatePage(string $title): SeoData
    {
        return $this->make(
            title: $title,
            description: (string) config('seo.default_description'),
            canonicalPath: request()->path(),
            robots: 'noindex,nofollow',
        );
    }

    /**
     * @param  array<string, mixed>|null  $jsonLd
     */
    private function make(
        string $title,
        string $description,
        string $canonicalPath,
        string $robots,
        string $ogType = 'website',
        ?string $ogImage = null,
        ?array $jsonLd = null,
    ): SeoData {
        $suffix = (string) config('seo.title_suffix', ' | LUT Web');
        $fullTitle = Str::endsWith($title, $suffix) ? $title : $title.$suffix;

        return new SeoData(
            title: $fullTitle,
            description: Str::limit(strip_tags($description), 300, ''),
            canonicalUrl: $this->absolute($canonicalPath),
            robots: $robots,
            ogTitle: $fullTitle,
            ogDescription: Str::limit(strip_tags($description), 300, ''),
            ogType: $ogType,
            ogImage: $ogImage ?: config('seo.default_og_image'),
            jsonLd: $jsonLd,
        );
    }

    private function publicRobots(): string
    {
        return (bool) config('seo.indexing_enabled', false) ? 'index,follow' : 'noindex,follow';
    }

    private function absolute(string $path): string
    {
        $base = trim((string) config('seo.canonical_url', ''), '/');

        if ($base !== '') {
            return $base.'/'.ltrim($path, '/');
        }

        return URL::to($path === '/' ? '/' : '/'.ltrim($path, '/'));
    }

    /**
     * @return array<string, mixed>
     */
    private function productJsonLd(Product $product, ?string $image): array
    {
        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $product->name,
            'description' => strip_tags($product->description ?: $product->short_description),
            'url' => $this->absolute(route('shop.show', $product->slug, absolute: false)),
            'brand' => [
                '@type' => 'Brand',
                'name' => (string) config('seo.organization.name', 'LUT Web'),
            ],
            'offers' => [
                '@type' => 'Offer',
                'priceCurrency' => $product->currency,
                'price' => number_format($product->price_cents / 100, 2, '.', ''),
                'availability' => 'https://schema.org/InStock',
                'url' => $this->absolute(route('shop.show', $product->slug, absolute: false)),
            ],
        ];

        if ($product->sku) {
            $data['sku'] = $product->sku;
        }

        if ($image) {
            $data['image'] = [$image];
        }

        if ($product->relationLoaded('categories') && $product->categories->isNotEmpty()) {
            $data['category'] = $product->categories->first()->name;
        }

        return $data;
    }
}
