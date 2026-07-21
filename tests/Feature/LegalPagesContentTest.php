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
