<?php

namespace App\Services\LutWizard;

use App\Models\WizardProject;
use App\Models\WizardProjectPhoto;
use Illuminate\Support\Facades\DB;

class DeleteWizardProject
{
    public function __construct(private readonly DeleteWizardProjectPhoto $deletePhoto) {}

    public function delete(WizardProject $project): bool
    {
        return DB::transaction(function () use ($project): bool {
            $project->photos()
                ->get()
                ->each(fn (WizardProjectPhoto $photo): bool => $this->deletePhoto->delete($photo));

            $project->variants()->delete();

            return (bool) $project->delete();
        });
    }
}
