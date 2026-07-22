<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Services\Seo\SeoMetaFactory;
use App\Support\Storefront\FaqCatalog;
use Inertia\Inertia;
use Inertia\Response;

class FaqController extends Controller
{
    public function __invoke(FaqCatalog $faqCatalog, SeoMetaFactory $seo): Response
    {
        $sections = $faqCatalog->sections();
        $questions = [];

        foreach ($sections as $section) {
            foreach ($section['items'] as $item) {
                $questions[] = [
                    'question' => $item['question'],
                    'answer' => $item['answer'],
                ];
            }
        }

        return Inertia::render('Faq/Index', [
            'sections' => $sections,
            'question_count' => count($questions),
            'seo' => $seo->faq($questions)->toArray(),
        ]);
    }
}
