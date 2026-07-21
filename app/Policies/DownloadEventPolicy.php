<?php

namespace App\Policies;

use App\Models\DownloadEvent;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class DownloadEventPolicy
{
    public function view(User $user, DownloadEvent $downloadEvent): Response
    {
        return $downloadEvent->user_id === $user->id
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
