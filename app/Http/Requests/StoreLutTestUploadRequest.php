<?php

namespace App\Http\Requests;

use App\Enums\LutTestStatus;
use App\Rules\DecodablePreviewImage;
use App\Rules\MaximumImagePixelCount;
use App\Rules\SingleFrameRasterImage;
use App\Rules\ValidPreviewImageMime;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreLutTestUploadRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
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
        $maxKilobytes = (int) config('lut-tester.max_upload_mb', 20) * 1024;
        $maxEdge = (int) config('lut-tester.max_input_edge', 12_000);
        $minWidth = (int) config('lut-tester.min_width', 320);
        $minHeight = (int) config('lut-tester.min_height', 320);

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
                new MaximumImagePixelCount((int) config('lut-tester.max_pixels', 50_000_000)),
                new SingleFrameRasterImage,
                new DecodablePreviewImage,
            ],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $user = $this->user();

                if ($user === null || $validator->errors()->isNotEmpty()) {
                    return;
                }

                $activeCount = $user->lutTestUploads()
                    ->nonExpired()
                    ->whereIn('status', [
                        LutTestStatus::Queued->value,
                        LutTestStatus::Processing->value,
                        LutTestStatus::Ready->value,
                    ])
                    ->count();

                if ($activeCount >= (int) config('lut-tester.max_active_tests_per_user', 3)) {
                    $validator->errors()->add('photo', 'You already have the maximum number of active LUT tests. Delete an existing test or wait for it to expire.');
                }
            },
        ];
    }
}
