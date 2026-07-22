<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Support\Storefront\StorefrontPreviewAttributionCatalog;
use Inertia\Inertia;
use Inertia\Response;

class LicenseController extends Controller
{
    public function __invoke(StorefrontPreviewAttributionCatalog $attributionCatalog): Response
    {
        $categories = $attributionCatalog->categories();

        return Inertia::render('Legal/License', [
            'source_count' => array_sum(array_map(
                static fn (array $category): int => count($category['sources']),
                $categories,
            )),
            'source_attributions' => $categories,
        ]);
    }
}
