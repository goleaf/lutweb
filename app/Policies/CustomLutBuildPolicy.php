<?php

namespace App\Policies;

use App\Models\CustomLutBuild;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class CustomLutBuildPolicy
{
    public function view(User $user, CustomLutBuild $customLutBuild): Response
    {
        return $this->ownedActiveCustomerBuild($user, $customLutBuild)
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    public function purchase(User $user, CustomLutBuild $customLutBuild): Response
    {
        return $this->ownedActiveCustomerBuild($user, $customLutBuild) && $user->hasVerifiedEmail()
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    public function delete(User $user, CustomLutBuild $customLutBuild): Response
    {
        return $this->ownedActiveCustomerBuild($user, $customLutBuild) && $customLutBuild->mayBeDeleted()
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    private function ownedActiveCustomerBuild(User $user, CustomLutBuild $customLutBuild): bool
    {
        return $customLutBuild->user_id === $user->id && ! $user->is_suspended;
    }
}
