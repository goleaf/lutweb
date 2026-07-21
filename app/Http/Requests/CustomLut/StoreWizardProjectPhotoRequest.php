<?php

namespace App\Http\Requests\CustomLut;

use App\Rules\DecodablePreviewImage;
use App\Rules\MaximumImagePixelCount;
use App\Rules\SingleFrameRasterImage;
use App\Rules\ValidPreviewImageMime;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreWizardProjectPhotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->hasVerifiedEmail();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $maxKilobytes = (int) config('lut-wizard.upload.max_upload_mb', 20) * 1024;
        $maxEdge = (int) config('lut-wizard.upload.max_input_edge', 12_000);
        $minWidth = (int) config('lut-wizard.upload.min_width', 320);
        $minHeight = (int) config('lut-wizard.upload.min_height', 320);

        return [
            'photo' => [
                'required',
                'file',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'mimetypes:image/jpeg,image/png,image/webp',
                'extensions:jpg,jpeg,png,webp',
                'max:'.$maxKilobytes,
                'dimensions:min_width='.$minWidth.',min_height='.$minHeight.',max_width='.$maxEdge.',max_height='.$maxEdge,
                new ValidPreviewImageMime,
                new MaximumImagePixelCount((int) config('lut-wizard.upload.max_pixels', 50_000_000)),
                new SingleFrameRasterImage,
                new DecodablePreviewImage,
            ],
        ];
    }
}
