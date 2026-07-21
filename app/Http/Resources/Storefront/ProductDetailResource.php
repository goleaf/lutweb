<?php

namespace App\Http\Resources\Storefront;

use App\Enums\ProductFileKind;
use App\Enums\ProductType;
use App\Enums\ProductVersionStatus;
use App\Models\BundleItem;
use App\Models\Category;
use App\Models\CompatibleSoftware;
use App\Models\Product;
use App\Models\ProductExample;
use App\Models\ProductFile;
use App\Models\ProductMedia;
use App\Models\ProductVersion;
use App\Models\Tag;
use App\Support\Catalog\EurMoney;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ProductDetailResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $product = $this->resource;

        if (! $product instanceof Product) {
            return [];
        }

        $media = collect([$this->publicMedia($product->coverMedia)])
            ->filter(fn (?ProductMedia $media): bool => $media !== null)
            ->merge($product->galleryMedia)
            ->filter(fn (ProductMedia $media): bool => $media->disk === 'public')
            ->map(fn (ProductMedia $media): array => (new ProductMediaResource($media))->toArray($request))
            ->values()
            ->all();

        return [
            'id' => $product->id,
            'type' => $product->type->value,
            'type_label' => $product->type->label(),
            'name' => $product->name,
            'slug' => $product->slug,
            'url' => route('shop.show', $product->slug),
            'short_description' => $product->short_description,
            'description' => $product->description,
            'formatted_price' => $product->isFree() ? 'Free' : '€'.EurMoney::formatCents($product->price_cents),
            'is_free' => $product->isFree(),
            'currency' => $product->currency,
            'is_featured' => $product->is_featured,
            'published_at' => $product->published_at?->toISOString(),
            'cover' => $this->publicMedia($product->coverMedia)
                ? (new ProductMediaResource($product->coverMedia))->toArray($request)
                : null,
            'media' => $media,
            'examples' => $this->examples($product),
            'package_contents' => $this->packageContents($product->currentVersion),
            'availability_message' => $this->availabilityMessage($product->currentVersion),
            'categories' => $this->categories($product),
            'tags' => $this->tags($product),
            'compatible_software' => $this->compatibleSoftware($product),
            'bundle_items' => $this->bundleItems($product, $request),
            'seo' => [
                'title' => $product->meta_title ?: $product->name,
                'description' => $product->meta_description ?: $product->short_description,
                'canonical_url' => route('shop.show', $product->slug),
                'image' => $this->publicMedia($product->coverMedia)
                    ? Storage::disk('public')->url($product->coverMedia->path)
                    : null,
            ],
        ];
    }

    /**
     * @return array<int, array{id: int, title: string|null, before: array{url: string, alt_text: string}, after: array{url: string, alt_text: string}}>
     */
    private function examples(Product $product): array
    {
        return $product->activeExamples
            ->filter(fn (ProductExample $example): bool => $example->before_disk === 'public' && $example->after_disk === 'public')
            ->map(fn (ProductExample $example): array => [
                'id' => $example->id,
                'title' => $example->title,
                'before' => [
                    'url' => Storage::disk('public')->url($example->before_path),
                    'alt_text' => $example->before_alt_text,
                ],
                'after' => [
                    'url' => Storage::disk('public')->url($example->after_path),
                    'alt_text' => $example->after_alt_text,
                ],
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function packageContents(?ProductVersion $version): array
    {
        if ($version === null || $version->status !== ProductVersionStatus::Ready) {
            return [];
        }

        return $version->files
            ->map(fn (ProductFile $file): ?string => match ($file->kind) {
                ProductFileKind::Cube17 => '17-point CUBE LUT',
                ProductFileKind::Cube33 => '33-point CUBE LUT',
                ProductFileKind::Cube65 => '65-point CUBE LUT',
                ProductFileKind::PackageZip => 'ZIP package',
                ProductFileKind::LicensePdf => 'License PDF',
                ProductFileKind::GuidePdf => 'Installation guide',
                ProductFileKind::Readme => 'README',
                ProductFileKind::SourceCube => null,
            })
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function availabilityMessage(?ProductVersion $version): ?string
    {
        if ($version === null) {
            return 'Package details are being prepared.';
        }

        if ($version->status !== ProductVersionStatus::Ready) {
            return 'The current package is being prepared.';
        }

        return null;
    }

    /**
     * @return array<int, array{id: int, name: string, slug: string, description: string|null, url: string, products_count: int|null}>
     */
    private function categories(Product $product): array
    {
        return $product->categories
            ->filter(fn (Category $category): bool => $category->is_active)
            ->map(fn (Category $category): array => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'description' => $category->description,
                'url' => route('categories.show', $category->slug),
                'products_count' => null,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{id: int, name: string, slug: string, products_count: int|null}>
     */
    private function tags(Product $product): array
    {
        return $product->tags
            ->map(fn (Tag $tag): array => [
                'id' => $tag->id,
                'name' => $tag->name,
                'slug' => $tag->slug,
                'products_count' => null,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{id: int, name: string, slug: string, website_url: string|null, products_count: int|null}>
     */
    private function compatibleSoftware(Product $product): array
    {
        return $product->compatibleSoftware
            ->map(fn (CompatibleSoftware $software): array => [
                'id' => $software->id,
                'name' => $software->name,
                'slug' => $software->slug,
                'website_url' => $software->website_url,
                'products_count' => null,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{id: int, name: string, url: string|null, cover: array<string, mixed>|null}>
     */
    private function bundleItems(Product $product, Request $request): array
    {
        if (! $product->isBundle()) {
            return [];
        }

        return $product->bundleItems
            ->filter(fn (BundleItem $item): bool => $item->product !== null && $item->product->type !== ProductType::Bundle)
            ->map(fn (BundleItem $item): array => [
                'id' => $item->product->id,
                'name' => $item->product->name,
                'url' => $item->product->isPublished() ? route('shop.show', $item->product->slug) : null,
                'cover' => $this->publicMedia($item->product->coverMedia)
                    ? (new ProductMediaResource($item->product->coverMedia))->toArray($request)
                    : null,
            ])
            ->values()
            ->all();
    }

    private function publicMedia(?ProductMedia $media): ?ProductMedia
    {
        if ($media === null || $media->disk !== 'public') {
            return null;
        }

        return $media;
    }
}
