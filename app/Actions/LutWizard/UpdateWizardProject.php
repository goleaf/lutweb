<?php

namespace App\Actions\LutWizard;

use App\Models\User;
use App\Models\WizardProject;
use App\Services\LutWizard\ValidateWizardProjectParameters;
use App\Services\LutWizard\WizardProjectMutator;
use App\ValueObjects\LutTransformParameters;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class UpdateWizardProject
{
    public function __construct(
        private readonly ValidateWizardProjectParameters $validateParameters,
        private readonly WizardProjectMutator $mutator,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(WizardProject $project, User $user, array $data): WizardProject
    {
        return $this->mutator->mutate(
            $project,
            $user,
            (int) $data['expected_revision'],
            (string) $data['mutation_id'],
            function (WizardProject $project) use ($data): void {
                if (array_key_exists('name', $data)) {
                    $this->validateName((string) $data['name']);
                    $project->name = (string) $data['name'];
                }

                if (array_key_exists('parameters', $data)) {
                    $parameters = $this->parametersFromData($data['parameters']);
                    $this->validateParameters->validate($project, $parameters);
                    $project->setParameters($parameters);
                }
            },
            autosave: true,
        );
    }

    private function validateName(string $name): void
    {
        if (mb_strlen($name) > 80) {
            throw ValidationException::withMessages([
                'name' => 'The project name may not be greater than 80 characters.',
            ]);
        }

        if (preg_match('/[\x00-\x1F\x7F]/', $name) === 1) {
            throw ValidationException::withMessages([
                'name' => 'The project name may not contain control characters.',
            ]);
        }
    }

    private function parametersFromData(mixed $parameters): LutTransformParameters
    {
        if (! is_array($parameters)) {
            throw ValidationException::withMessages([
                'parameters' => 'The parameters field must be an object.',
            ]);
        }

        try {
            return LutTransformParameters::fromArray($parameters);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'parameters' => $exception->getMessage(),
            ]);
        }
    }
}
