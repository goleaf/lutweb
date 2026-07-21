<?php

namespace App\Support\Storefront;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

class StorefrontFilterData
{
    /**
     * @var array<int, string>
     */
    private const PRODUCT_TYPES = ['all', 'single_lut', 'bundle', 'free_lut'];

    /**
     * @var array<int, string>
     */
    private const PRICING_OPTIONS = ['all', 'free', 'paid'];

    /**
     * @var array<int, string>
     */
    private const SORT_OPTIONS = ['featured', 'newest', 'price_asc', 'price_desc', 'name_asc'];

    public function __construct(
        public readonly ?string $q,
        public readonly ?string $category,
        public readonly ?string $tag,
        public readonly ?string $software,
        public readonly string $type,
        public readonly string $pricing,
        public readonly string $sort,
    ) {}

    public static function fromRequest(Request $request, ?string $fixedCategorySlug = null): self
    {
        return new self(
            q: self::queryText($request, 'q'),
            category: $fixedCategorySlug !== null ? self::slug($fixedCategorySlug) : self::querySlug($request, 'category'),
            tag: self::querySlug($request, 'tag'),
            software: self::querySlug($request, 'software'),
            type: self::option($request, 'type', self::PRODUCT_TYPES, 'all'),
            pricing: self::option($request, 'pricing', self::PRICING_OPTIONS, 'all'),
            sort: self::option($request, 'sort', self::SORT_OPTIONS, 'featured'),
        );
    }

    public function forCategory(string $categorySlug): self
    {
        return new self(
            q: $this->q,
            category: self::slug($categorySlug),
            tag: $this->tag,
            software: $this->software,
            type: $this->type,
            pricing: $this->pricing,
            sort: $this->sort,
        );
    }

    public function isFiltered(?string $baseCategory = null): bool
    {
        return $this->q !== null
            || $this->tag !== null
            || $this->software !== null
            || $this->type !== 'all'
            || $this->pricing !== 'all'
            || $this->sort !== 'featured'
            || ($baseCategory === null && $this->category !== null);
    }

    /**
     * @return array{q: string|null, category: string|null, tag: string|null, software: string|null, type: string, pricing: string, sort: string}
     */
    public function toArray(): array
    {
        return [
            'q' => $this->q,
            'category' => $this->category,
            'tag' => $this->tag,
            'software' => $this->software,
            'type' => $this->type,
            'pricing' => $this->pricing,
            'sort' => $this->sort,
        ];
    }

    private static function queryText(Request $request, string $key): ?string
    {
        $value = $request->query($key);

        if (! is_scalar($value)) {
            return null;
        }

        $text = Str::of((string) $value)->squish()->limit(100, '')->toString();

        return $text === '' ? null : $text;
    }

    private static function querySlug(Request $request, string $key): ?string
    {
        $value = $request->query($key);

        if (! is_scalar($value)) {
            return null;
        }

        return self::slug((string) $value);
    }

    private static function slug(string $value): ?string
    {
        $slug = Str::of($value)->trim()->lower()->toString();

        if ($slug === '' || ! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
            return null;
        }

        return $slug;
    }

    /**
     * @param  array<int, string>  $allowed
     */
    private static function option(Request $request, string $key, array $allowed, string $default): string
    {
        $value = $request->query($key);

        if (! is_scalar($value)) {
            return $default;
        }

        $option = Str::of((string) $value)->trim()->lower()->toString();

        return in_array($option, $allowed, true) ? $option : $default;
    }
}
