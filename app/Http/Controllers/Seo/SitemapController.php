<?php

namespace App\Http\Controllers\Seo;

use App\Http\Controllers\Controller;
use App\Services\Seo\BuildSitemapIndex;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function __invoke(BuildSitemapIndex $sitemap): Response
    {
        return response($sitemap->xml(), 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
