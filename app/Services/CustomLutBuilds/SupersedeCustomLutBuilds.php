<?php

namespace App\Services\CustomLutBuilds;

use App\Enums\CustomLutBuildStatus;
use App\Models\CustomLutBuild;
use App\Models\WizardProject;

class SupersedeCustomLutBuilds
{
    public function __construct(private readonly DeleteCustomLutBuild $deleteBuild) {}

    public function handle(WizardProject $project): void
    {
        $project->customLutBuilds()
            ->with(['files', 'orderItems', 'entitlements'])
            ->whereIn('status', [
                CustomLutBuildStatus::Queued->value,
                CustomLutBuildStatus::Processing->value,
                CustomLutBuildStatus::Ready->value,
            ])
            ->get()
            ->each(function (CustomLutBuild $build): void {
                if ($build->hasBeenPurchased()) {
                    $build->forceFill(['is_current' => false])->save();

                    return;
                }

                if ($build->mayBeDeleted()) {
                    $this->deleteBuild->deleteFiles($build);
                }

                $build->forceFill([
                    'status' => CustomLutBuildStatus::Superseded,
                    'is_current' => false,
                    'sale_ready' => false,
                    'superseded_at' => now(),
                ])->save();
            });
    }
}
