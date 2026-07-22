<?php

use App\Support\Storefront\FaqCatalog;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;

test('faq page publishes a comprehensive searchable catalog with structured data', function (): void {
    config(['seo.canonical_url' => 'https://lut-web.example']);

    $sections = app(FaqCatalog::class)->sections();
    $items = collect($sections)->flatMap(fn (array $section): array => $section['items']);

    expect($sections)->toHaveCount(16)
        ->and($items)->toHaveCount(160)
        ->and($items->pluck('id')->unique())->toHaveCount(160)
        ->and($items->pluck('question')->unique())->toHaveCount(160)
        ->and($items->every(fn (array $item): bool => mb_strlen($item['answer']) >= 80))->toBeTrue();

    $this->get(route('faq'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Faq/Index')
            ->has('sections', 16)
            ->where('question_count', 160)
            ->where('seo.canonical_url', 'https://lut-web.example/faq')
            ->where('seo.json_ld.@type', 'FAQPage')
            ->has('seo.json_ld.mainEntity', 160));
});

test('faq is linked from the sitemap and product pages no longer embed a local faq', function (): void {
    Cache::forget('seo:sitemap:index');
    config(['seo.canonical_url' => 'https://lut-web.example']);

    $xml = $this->get(route('sitemap.index'))->assertOk()->getContent();
    $productPage = file_get_contents(resource_path('js/pages/Shop/Show.vue'));

    expect($xml)->toContain('https://lut-web.example/faq')
        ->and($productPage)->not->toContain('ProductFaq');
});
