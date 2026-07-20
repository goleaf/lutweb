<?php

use App\Filament\Resources\Categories\CategoryResource;
use App\Filament\Resources\CompatibleSoftware\CompatibleSoftwareResource;
use App\Filament\Resources\Products\ProductResource;
use App\Filament\Resources\Tags\TagResource;
use App\Models\Product;
use App\Models\User;

test('Filament resource list pages render for a verified admin', function (string $url) {
    $admin = User::factory()->admin()->verified()->create();

    $this->actingAs($admin)
        ->get($url)
        ->assertOk();
})->with([
    'products' => fn () => ProductResource::getUrl('index'),
    'categories' => fn () => CategoryResource::getUrl('index'),
    'tags' => fn () => TagResource::getUrl('index'),
    'compatible software' => fn () => CompatibleSoftwareResource::getUrl('index'),
]);

test('Non-admin users cannot execute Filament resource operations', function () {
    $user = User::factory()->verified()->create();
    $product = Product::factory()->singleLut()->create();

    $this->actingAs($user)
        ->get(ProductResource::getUrl('create'))
        ->assertForbidden();

    $this->actingAs($user)
        ->get(ProductResource::getUrl('edit', ['record' => $product]))
        ->assertForbidden();
});
