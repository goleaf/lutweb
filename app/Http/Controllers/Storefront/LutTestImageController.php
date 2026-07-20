<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\LutTestUpload;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LutTestImageController extends Controller
{
    public function __invoke(LutTestUpload $lutTestUpload, string $variant): StreamedResponse|Response
    {
        if (! in_array($variant, ['before', 'after'], true)) {
            abort(404);
        }

        Gate::authorize('viewImage', $lutTestUpload);

        $path = $variant === 'before'
            ? $lutTestUpload->before_preview_path
            : $lutTestUpload->after_preview_path;

        if ($path === null) {
            abort(404);
        }

        $disk = Storage::disk($lutTestUpload->disk);

        if (! $disk->exists($path)) {
            abort(404);
        }

        $stream = $disk->readStream($path);

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
