<?php

namespace App\Services\CustomLutBuilds;

use App\Enums\PackageDocumentKind;
use App\Enums\PackageDocumentStatus;
use App\Models\PackageDocumentTemplate;

final readonly class PackageDocumentSnapshot
{
    public function __construct(
        public string $id,
        public PackageDocumentKind $kind,
        public PackageDocumentStatus $status,
        public string $version,
        public string $title,
        public string $body,
        public bool $isCurrent,
        public string $contentHash,
    ) {}

    public static function fromTemplate(PackageDocumentTemplate $template): self
    {
        return new self(
            id: $template->id,
            kind: $template->kind,
            status: $template->status,
            version: $template->version,
            title: $template->title,
            body: $template->body,
            isCurrent: $template->is_current,
            contentHash: $template->contentHash(),
        );
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    public static function fromArray(array $snapshot): self
    {
        return new self(
            id: (string) $snapshot['id'],
            kind: PackageDocumentKind::from((string) $snapshot['kind']),
            status: PackageDocumentStatus::from((string) $snapshot['status']),
            version: (string) $snapshot['version'],
            title: (string) $snapshot['title'],
            body: (string) $snapshot['body'],
            isCurrent: (bool) $snapshot['is_current'],
            contentHash: (string) $snapshot['content_hash'],
        );
    }

    public function isDraft(): bool
    {
        return $this->status === PackageDocumentStatus::Draft || str_starts_with($this->version, 'draft-');
    }

    public function mayBeUsedForSaleBuild(): bool
    {
        return $this->status === PackageDocumentStatus::Active
            && $this->isCurrent
            && $this->version !== ''
            && ! str_starts_with($this->version, 'draft-');
    }
}
