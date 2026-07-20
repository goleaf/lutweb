<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;
use Illuminate\Translation\PotentiallyTranslatedString;

class MaximumImagePixelCount implements ValidationRule
{
    public function __construct(
        private readonly int $maxPixels,
    ) {}

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile) {
            $fail('The photo must be a valid uploaded image.');

            return;
        }

        $size = @getimagesize($value->getRealPath() ?: '');

        if ($size === false) {
            $fail('The photo must be a valid image.');

            return;
        }

        [$width, $height] = $size;

        if ($width * $height > $this->maxPixels) {
            $fail('The photo is too large. Please upload an image with fewer pixels.');
        }
    }
}
