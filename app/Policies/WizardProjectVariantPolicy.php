<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WizardProjectVariant;
use Illuminate\Auth\Access\Response;

class WizardProjectVariantPolicy
{
    public function view(User $user, WizardProjectVariant $wizardProjectVariant): Response
    {
        $project = $wizardProjectVariant->wizardProject;

        return $project !== null && $project->belongsToUser($user) && ! $user->is_suspended
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    public function select(User $user, WizardProjectVariant $wizardProjectVariant): Response
    {
        $project = $wizardProjectVariant->wizardProject;

        return $project !== null && $project->mayBeEditedBy($user)
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
