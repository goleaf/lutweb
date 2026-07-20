<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;
use Illuminate\Translation\PotentiallyTranslatedString;

class ValidPreviewImageMime implements ValidationRule
{
    /**
     * @var array<int, string>
     */
    private array $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];

    /**
     * @var array<int, string>
     */
    private array $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile || ! $value->isValid()) {
            $fail('The photo must be a successful uploaded file.');

            return;
        }

        $extension = strtolower($value->getClientOriginalExtension());

        if (! in_array($extension, $this->allowedExtensions, true)) {
            $fail('The photo must be a JPG, PNG, or WebP image.');

            return;
        }

        $realPath = $value->getRealPath();

        if ($realPath === false) {
            $fail('The photo could not be inspected.');

            return;
        }

        $fileInfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = $fileInfo ? finfo_file($fileInfo, $realPath) : false;

        if ($fileInfo) {
            finfo_close($fileInfo);
        }

        if (! is_string($mimeType) || ! in_array($mimeType, $this->allowedMimes, true)) {
            $fail('The photo content must be JPG, PNG, or WebP.');
        }
    }
}
