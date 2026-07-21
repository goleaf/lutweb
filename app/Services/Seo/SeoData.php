<?php

namespace App\Services\Seo;

final readonly class SeoData
{
    /**
     * @param  array<string, mixed>|null  $jsonLd
     */
    public function __construct(
        public string $title,
        public string $description,
        public string $canonicalUrl,
        public string $robots,
        public string $ogTitle,
        public string $ogDescription,
        public string $ogType = 'website',
        public ?string $ogImage = null,
        public string $twitterCard = 'summary_large_image',
        public ?array $jsonLd = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'description' => $this->description,
            'canonical_url' => $this->canonicalUrl,
            'robots' => $this->robots,
            'og_title' => $this->ogTitle,
            'og_description' => $this->ogDescription,
            'og_type' => $this->ogType,
            'og_image' => $this->ogImage,
            'twitter_card' => $this->twitterCard,
            'json_ld' => $this->jsonLd,
        ];
    }
}
