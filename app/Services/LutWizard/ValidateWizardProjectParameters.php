<?php

namespace App\Services\LutWizard;

use App\Models\WizardProject;
use App\ValueObjects\LutTransformParameters;
use Illuminate\Validation\ValidationException;

class ValidateWizardProjectParameters
{
    public function validate(WizardProject $project, LutTransformParameters $parameters): void
    {
        if (! is_array($project->style_snapshot)) {
            return;
        }

        $minimum = LutTransformParameters::fromArray((array) $project->style_snapshot['minimum_parameters']);
        $maximum = LutTransformParameters::fromArray((array) $project->style_snapshot['maximum_parameters']);
        $values = $parameters->toArray();
        $errors = [];

        foreach (LutTransformParameters::keys() as $key) {
            if (LutTransformParameters::isHueKey($key)) {
                continue;
            }

            if ($values[$key] < $minimum->toArray()[$key] || $values[$key] > $maximum->toArray()[$key]) {
                $errors["parameters.{$key}"] = "The {$key} value is outside the selected style range.";
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
