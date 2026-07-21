<?php

namespace App\Actions\CustomLutBuilds;

use App\Enums\PackageDocumentStatus;
use App\Models\PackageDocumentTemplate;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ActivatePackageDocumentTemplate
{
    public function handle(PackageDocumentTemplate $template): PackageDocumentTemplate
    {
        if ($template->trashed()) {
            throw ValidationException::withMessages([
                'template' => 'A deleted document template cannot be activated.',
            ]);
        }

        return DB::transaction(function () use ($template): PackageDocumentTemplate {
            $lockedTemplate = PackageDocumentTemplate::query()
                ->whereKey($template->id)
                ->lockForUpdate()
                ->firstOrFail();

            PackageDocumentTemplate::query()
                ->where('kind', $lockedTemplate->kind->value)
                ->whereKeyNot($lockedTemplate->id)
                ->update(['is_current' => false]);

            $lockedTemplate->forceFill([
                'status' => PackageDocumentStatus::Active,
                'is_current' => true,
                'activated_at' => now(),
            ])->save();

            return $lockedTemplate;
        });
    }
}
