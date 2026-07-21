<?php

namespace App\Http\Controllers\Seo;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

class RobotsController extends Controller
{
    public function __invoke(): Response
    {
        if (! (bool) config('seo.indexing_enabled', false)) {
            return response("User-agent: *\nDisallow: /\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }

        $lines = [
            'User-agent: *',
            'Allow: /',
            'Disallow: /admin',
            'Disallow: /account',
            'Disallow: /login',
            'Disallow: /register',
            'Disallow: /forgot-password',
            'Disallow: /reset-password',
            'Disallow: /email',
            'Disallow: /checkout',
            'Disallow: /webhooks',
            'Disallow: /custom-lut',
            'Disallow: /lut-tests',
            'Sitemap: '.$this->sitemapUrl(),
            '',
        ];

        return response(implode("\n", $lines), 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    private function sitemapUrl(): string
    {
        $canonicalUrl = trim((string) config('seo.canonical_url', ''), '/');

        if ($canonicalUrl !== '') {
            return $canonicalUrl.'/sitemap.xml';
        }

        return route('sitemap.index');
    }
}
