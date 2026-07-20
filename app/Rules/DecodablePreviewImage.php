<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;
use Illuminate\Translation\PotentiallyTranslatedString;
use Intervention\Image\Drivers\Imagick\Driver;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageManagerInterface;

class DecodablePreviewImage implements ValidationRule
{
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

        try {
            $image = $this->imageManager()->decodePath($value->getRealPath() ?: '');
            $width = $image->width();
            $height = $image->height();

            if ($width < (int) config('lut-tester.min_width', 320) || $height < (int) config('lut-tester.min_height', 320)) {
                $fail('The photo must be at least 320 by 320 pixels.');
            }
        } catch (\Throwable) {
            $fail('The photo could not be decoded by the configured image driver.');
        }
    }

    private function imageManager(): ImageManagerInterface
    {
        $driver = strcasecmp((string) config('lut-tester.image_driver', 'gd'), 'imagick') === 0
            ? Driver::class
            : \Intervention\Image\Drivers\Gd\Driver::class;

        return ImageManager::usingDriver($driver);
    }
}
