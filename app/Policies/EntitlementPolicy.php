<?php

namespace App\Policies;

use App\Models\Entitlement;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class EntitlementPolicy
{
    public function view(User $user, Entitlement $entitlement): Response
    {
        return $entitlement->user_id === $user->id
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    public function download(User $user, Entitlement $entitlement): Response
    {
        return $entitlement->mayBeDownloadedBy($user)
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
