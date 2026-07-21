<?php

namespace App\Http\Controllers\CustomLut;

use App\Http\Controllers\Controller;
use App\Models\WizardProject;
use App\Models\WizardProjectPhoto;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProjectPhotoPreviewController extends Controller
{
    public function __invoke(WizardProject $wizardProject, WizardProjectPhoto $wizardProjectPhoto): StreamedResponse|Response
    {
        abort_unless($wizardProjectPhoto->wizard_project_id === $wizardProject->id, 404);
        Gate::authorize('view', $wizardProjectPhoto);

        if (! $wizardProjectPhoto->isReady() || $wizardProjectPhoto->preview_path === null) {
            abort(404);
        }

        $diskName = (string) config('lut-wizard.disk', 'private');
        abort_unless($wizardProjectPhoto->disk === $diskName, 404);

        $disk = Storage::disk($diskName);

        if (! $disk->exists($wizardProjectPhoto->preview_path)) {
            abort(404);
        }

        $stream = $disk->readStream($wizardProjectPhoto->preview_path);

        if ($stream === null) {
            abort(404);
        }

        return response()->stream(function () use ($stream): void {
            fpassthru($stream);
            fclose($stream);
        }, 200, [
            'Content-Type' => 'image/webp',
            'Content-Disposition' => 'inline',
            'Cache-Control' => 'private, no-store, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
