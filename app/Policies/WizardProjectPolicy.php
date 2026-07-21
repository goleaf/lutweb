<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WizardProject;
use Illuminate\Auth\Access\Response;

class WizardProjectPolicy
{
    public function view(User $user, WizardProject $wizardProject): Response
    {
        return $wizardProject->belongsToUser($user) && ! $user->is_suspended
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    public function update(User $user, WizardProject $wizardProject): Response
    {
        return $wizardProject->mayBeEditedBy($user)
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    public function build(User $user, WizardProject $wizardProject): Response
    {
        return $wizardProject->mayBeEditedBy($user) && $user->hasVerifiedEmail()
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    public function delete(User $user, WizardProject $wizardProject): Response
    {
        return $wizardProject->mayBeEditedBy($user)
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    public function duplicate(User $user, WizardProject $wizardProject): Response
    {
        return $wizardProject->mayBeEditedBy($user)
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
