<?php

use Inertia\Testing\AssertableInertia as Assert;

test('terms and privacy pages render through Inertia', function (string $routeName, string $component): void {
    $this->get(route($routeName))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component($component));
})->with([
    'terms' => ['terms', 'Legal/Terms'],
    'privacy' => ['privacy', 'Legal/Privacy'],
]);

test('terms and privacy pages contain production-facing copy', function (): void {
    $terms = file_get_contents(resource_path('js/pages/Legal/Terms.vue'));
    $privacy = file_get_contents(resource_path('js/pages/Legal/Privacy.vue'));

    expect($terms)->toContain('Acceptable use')
        ->and($terms)->toContain('Digital products and downloads')
        ->and($terms)->not->toContain('Final Terms of Use text will be added')
        ->and($privacy)->toContain('Information we collect')
        ->and($privacy)->toContain('Your choices and rights')
        ->and($privacy)->not->toContain('Final Privacy Policy text will be added');
});

test('license page publishes complete reusable image source credits', function (): void {
    $this->get(route('license'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Legal/License')
            ->where('source_count', 300)
            ->has('source_attributions', 10)
            ->where('source_attributions.0.slug', 'cinematic')
            ->where('source_attributions.0.name', 'Cinematic')
            ->has('source_attributions.0.sources', 30)
            ->where('source_attributions.0.sources.0.key', 'cinematic/001')
            ->where('source_attributions.0.sources.0.modification', 'Cropped and resized to 1600×1200; color unchanged.')
            ->where('source_attributions.9.slug', 'pastel')
            ->has('source_attributions.9.sources', 30));
});
