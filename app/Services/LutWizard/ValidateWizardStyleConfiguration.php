<?php

namespace App\Services\LutWizard;

use App\ValueObjects\LutTransformParameters;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class ValidateWizardStyleConfiguration
{
    /**
     * @param  array<mixed>  $base
     * @param  array<mixed>  $minimum
     * @param  array<mixed>  $maximum
     * @param  array<mixed>  $variationAmounts
     */
    public function validate(array $base, array $minimum, array $maximum, array $variationAmounts): void
    {
        try {
            $baseParameters = LutTransformParameters::fromArray($base);
            $minimumParameters = LutTransformParameters::fromArray($minimum);
            $maximumParameters = LutTransformParameters::fromArray($maximum);
            $this->validateVariationAmounts($variationAmounts);
            $this->validateRanges($baseParameters, $minimumParameters, $maximumParameters);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'parameters' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @param  array<mixed>  $variationAmounts
     */
    private function validateVariationAmounts(array $variationAmounts): void
    {
        $expected = LutTransformParameters::keys();
        $actual = array_keys($variationAmounts);
        $missing = array_values(array_diff($expected, $actual));
        $unknown = array_values(array_diff($actual, $expected));

        if ($missing !== []) {
            throw new InvalidArgumentException('Missing variation amount keys: '.implode(', ', $missing).'.');
        }

        if ($unknown !== []) {
            throw new InvalidArgumentException('Unknown variation amount keys: '.implode(', ', $unknown).'.');
        }

        foreach ($expected as $key) {
            $amount = $variationAmounts[$key];

            if (! is_int($amount)) {
                throw new InvalidArgumentException("The {$key} variation amount must be an integer.");
            }

            if ($amount < 0) {
                throw new InvalidArgumentException("The {$key} variation amount must be non-negative.");
            }

            if (LutTransformParameters::isHueKey($key) && $amount > 1800) {
                throw new InvalidArgumentException("The {$key} hue variation amount cannot exceed 180 degrees.");
            }

            if (! LutTransformParameters::isHueKey($key) && $amount > LutTransformParameters::span($key)) {
                throw new InvalidArgumentException("The {$key} variation amount cannot exceed the global parameter span.");
            }
        }
    }

    private function validateRanges(
        LutTransformParameters $baseParameters,
        LutTransformParameters $minimumParameters,
        LutTransformParameters $maximumParameters,
    ): void {
        foreach (LutTransformParameters::keys() as $key) {
            if (LutTransformParameters::isHueKey($key)) {
                continue;
            }

            $base = $baseParameters->toArray()[$key];
            $minimum = $minimumParameters->toArray()[$key];
            $maximum = $maximumParameters->toArray()[$key];

            if ($minimum > $base) {
                throw new InvalidArgumentException("The {$key} minimum cannot be greater than the base value.");
            }

            if ($base > $maximum) {
                throw new InvalidArgumentException("The {$key} base cannot be greater than the maximum value.");
            }
        }
    }
}
