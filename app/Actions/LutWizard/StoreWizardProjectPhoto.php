<?php

namespace App\Actions\LutWizard;

use App\Enums\WizardPhotoStatus;
use App\Jobs\ProcessWizardProjectPhoto;
use App\Models\User;
use App\Models\WizardProject;
use App\Models\WizardProjectPhoto;
use App\Services\LutWizard\DeleteWizardProjectPhoto;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class StoreWizardProjectPhoto
{
    public function __construct(
        private readonly DeleteWizardProjectPhoto $deletePhoto,
    ) {}

    public function handle(WizardProject $project, User $user, UploadedFile $file): WizardProjectPhoto
    {
        if (! $project->mayBeEditedBy($user)) {
            abort(404);
        }

        return DB::transaction(function () use ($project, $file): WizardProjectPhoto {
            $lockedProject = WizardProject::query()
                ->whereKey($project->id)
                ->with(['photos' => fn ($query) => $query->orderBy('sort_order')])
                ->lockForUpdate()
                ->firstOrFail();

            foreach ($lockedProject->photos as $photo) {
                if ($photo->isExpired()) {
                    $this->deletePhoto->delete($photo);
                }
            }

            $lockedProject->unsetRelation('photos');
            $activePhotoSlots = $lockedProject->photos()
                ->where('expires_at', '>', now())
                ->whereNot('status', WizardPhotoStatus::Expired->value)
                ->pluck('sort_order')
                ->all();

            $maximum = min(3, (int) config('lut-wizard.maximum_photos_per_project', 3));
            $slot = collect(range(1, $maximum))
                ->first(fn (int $candidate): bool => ! in_array($candidate, $activePhotoSlots, true));

            if ($slot === null) {
                throw ValidationException::withMessages([
                    'photo' => 'This project already has three active photos.',
                ]);
            }

            $imageSize = getimagesize($file->getRealPath());

            if ($imageSize === false) {
                throw ValidationException::withMessages([
                    'photo' => 'The photo could not be decoded.',
                ]);
            }

            $photo = new WizardProjectPhoto([
                'status' => WizardPhotoStatus::Queued,
                'disk' => (string) config('lut-wizard.disk', 'private'),
                'original_name' => $file->getClientOriginalName(),
                'original_mime_type' => (string) $file->getMimeType(),
                'original_size_bytes' => $file->getSize(),
                'original_width' => (int) $imageSize[0],
                'original_height' => (int) $imageSize[1],
                'sort_order' => $slot,
                'expires_at' => now()->addMinutes((int) config('lut-wizard.photo_expiration_minutes', 60)),
            ]);

            $photo->wizardProject()->associate($lockedProject);
            $photo->save();

            $rawPath = $this->rawPath($lockedProject, $photo, $file);
            Storage::disk($photo->disk)->putFileAs(dirname($rawPath), $file, basename($rawPath));

            $photo->forceFill(['raw_path' => $rawPath])->save();

            ProcessWizardProjectPhoto::dispatch($photo)->afterCommit();

            return $photo;
        });
    }

    private function rawPath(WizardProject $project, WizardProjectPhoto $photo, UploadedFile $file): string
    {
        $extension = strtolower((string) $file->extension());
        $extension = in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true) ? $extension : 'bin';

        return trim((string) config('lut-wizard.prefix', 'custom-lut-projects'), '/')
            .'/'.$project->user_id.'/'.$project->id.'/photos/'.$photo->id.'/raw/'.bin2hex(random_bytes(16)).'.'.$extension;
    }
}
