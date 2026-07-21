<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WizardProjectPhoto;
use Illuminate\Auth\Access\Response;

class WizardProjectPhotoPolicy
{
    public function view(User $user, WizardProjectPhoto $wizardProjectPhoto): Response
    {
        return $wizardProjectPhoto->mayBeViewedBy($user)
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    public function delete(User $user, WizardProjectPhoto $wizardProjectPhoto): Response
    {
        $project = $wizardProjectPhoto->wizardProject;

        return $project !== null && $project->mayBeEditedBy($user)
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
