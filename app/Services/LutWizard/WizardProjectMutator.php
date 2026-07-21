<?php

namespace App\Services\LutWizard;

use App\Models\User;
use App\Models\WizardProject;
use Closure;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;

class WizardProjectMutator
{
    /**
     * @param  Closure(WizardProject): void  $callback
     */
    public function mutate(
        WizardProject $project,
        User $user,
        int $expectedRevision,
        string $mutationId,
        Closure $callback,
        bool $autosave = false,
    ): WizardProject {
        return DB::transaction(function () use ($project, $user, $expectedRevision, $mutationId, $callback, $autosave): WizardProject {
            $lockedProject = WizardProject::query()
                ->whereKey($project->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $lockedProject->mayBeEditedBy($user)) {
                abort(404);
            }

            if ($lockedProject->last_mutation_id === $mutationId) {
                return $lockedProject;
            }

            if ($lockedProject->revision !== $expectedRevision) {
                throw new HttpResponseException(response()->json([
                    'message' => 'Updated in another tab.',
                    'project' => [
                        'id' => $lockedProject->id,
                        'name' => $lockedProject->name,
                        'revision' => $lockedProject->revision,
                        'parameters' => $lockedProject->parameters,
                        'parameters_hash' => $lockedProject->parameters_hash,
                    ],
                ], 409));
            }

            $callback($lockedProject);

            $lockedProject->revision++;
            $lockedProject->last_mutation_id = $mutationId;

            if ($autosave) {
                $lockedProject->last_autosaved_at = now();
            }

            $lockedProject->extendExpiration();
            $lockedProject->save();

            return $lockedProject;
        });
    }
}
