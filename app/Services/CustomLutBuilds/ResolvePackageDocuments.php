<?php

namespace App\Services\CustomLutBuilds;

use App\Enums\PackageDocumentKind;
use App\Models\PackageDocumentTemplate;
use Illuminate\Validation\ValidationException;

class ResolvePackageDocuments
{
    public function handle(): ResolvedPackageDocuments
    {
        return new ResolvedPackageDocuments(
            license: $this->resolve(PackageDocumentKind::License),
            guide: $this->resolve(PackageDocumentKind::InstallationGuide),
        );
    }

    private function resolve(PackageDocumentKind $kind): PackageDocumentSnapshot
    {
        $template = PackageDocumentTemplate::query()
            ->where('kind', $kind->value)
            ->where('is_current', true)
            ->latest('updated_at')
            ->first();

        if (! $template instanceof PackageDocumentTemplate || ! $template->mayBeUsedForReviewBuild()) {
            throw ValidationException::withMessages([
                'documents' => 'Final package documents are not configured.',
            ]);
        }

        return PackageDocumentSnapshot::fromTemplate($template);
    }
}
