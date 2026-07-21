<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;

#[Signature('seo:doctor')]
#[Description('Check SEO configuration, robots and sitemap routes.')]
class SeoDoctor extends Command
{
    public function handle(): int
    {
        $failed = false;

        if ($this->lineFor('SEO enabled', (bool) config('seo.enabled', true))) {
            $failed = true;
        }

        if ($this->lineFor('Canonical URL configured for production', ! app()->isProduction() || trim((string) config('seo.canonical_url', '')) !== '')) {
            $failed = true;
        }

        if ($this->lineFor('Indexing disabled outside production', app()->isProduction() || ! (bool) config('seo.indexing_enabled', false))) {
            $failed = true;
        }

        if ($this->lineFor('robots route exists', Route::has('robots'))) {
            $failed = true;
        }

        if ($this->lineFor('sitemap route exists', Route::has('sitemap.index'))) {
            $failed = true;
        }

        if ($this->lineFor('Sitemap URL limit valid', (int) config('seo.sitemap.max_urls', 50_000) <= 50_000)) {
            $failed = true;
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    private function lineFor(string $label, bool $passes): bool
    {
        $this->line(($passes ? 'PASS' : 'FAIL').' '.$label);

        return ! $passes;
    }
}
