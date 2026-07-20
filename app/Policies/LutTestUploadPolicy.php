<?php

namespace App\Policies;

use App\Models\LutTestUpload;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class LutTestUploadPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, LutTestUpload $lutTestUpload): bool
    {
        return $lutTestUpload->user_id === $user->id;
    }

    public function viewImage(User $user, LutTestUpload $lutTestUpload): Response
    {
        return $lutTestUpload->mayBeViewedBy($user) && $lutTestUpload->isReady()
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasVerifiedEmail();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, LutTestUpload $lutTestUpload): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, LutTestUpload $lutTestUpload): bool
    {
        return $lutTestUpload->user_id === $user->id;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, LutTestUpload $lutTestUpload): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, LutTestUpload $lutTestUpload): bool
    {
        return false;
    }
}
