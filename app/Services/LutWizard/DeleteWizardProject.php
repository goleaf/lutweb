<?php

namespace App\Services\LutWizard;

use App\Models\CustomLutBuild;
use App\Models\WizardProject;
use App\Models\WizardProjectPhoto;
use App\Services\CustomLutBuilds\DeleteCustomLutBuild;
use Illuminate\Support\Facades\DB;

class DeleteWizardProject
{
    public function __construct(
        private readonly DeleteWizardProjectPhoto $deletePhoto,
        private readonly DeleteCustomLutBuild $deleteBuild,
    ) {}

    public function delete(WizardProject $project): bool
    {
        return DB::transaction(function () use ($project): bool {
            $project->photos()
                ->get()
                ->each(fn (WizardProjectPhoto $photo): bool => $this->deletePhoto->delete($photo));

            $project->variants()->delete();

            $project->customLutBuilds()
                ->with(['files', 'orderItems', 'entitlements'])
                ->get()
                ->each(fn (CustomLutBuild $build): bool => $this->deleteOrRetainBuild($build));

            return (bool) $project->delete();
        });
    }

    private function deleteOrRetainBuild(CustomLutBuild $build): bool
    {
        if (! $build->mayBeDeleted()) {
            $build->forceFill(['wizard_project_id' => null])->save();

            return true;
        }

        $this->deleteBuild->deleteFiles($build);

        return (bool) $build->delete();
    }
}
