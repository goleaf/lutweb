<?php

test('storefront Inertia components have matching Vue page files', function (string $component) {
    $path = resource_path("js/pages/{$component}.vue");

    if (! file_exists($path)) {
        test()->fail("Missing Inertia page component: {$path}");
    }

    expect($path)->toBeFile();
})->with([
    'home page' => 'Home',
    'shop index page' => 'Shop/Index',
    'shop show page' => 'Shop/Show',
    'category show page' => 'Categories/Show',
]);
