<?php

namespace App\Services\Downloads;

use App\Enums\DownloadStatus;
use App\Models\DownloadEvent;
use App\Models\Entitlement;
use App\Models\User;
use App\Services\Checkout\ResolveEntitlementPackage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StreamEntitlementDownload
{
    public function __construct(
        private readonly ResolveEntitlementPackage $packages,
    ) {}

    public function handle(Request $request, Entitlement $entitlement): StreamedResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(404);
        }

        $package = $this->packages->handle($entitlement, $user);
        $disk = Storage::disk($package->disk);
        $item = $entitlement->orderItem;

        $event = DownloadEvent::query()->create([
            'entitlement_id' => $entitlement->id,
            'user_id' => $user->id,
            'order_id' => $entitlement->order_id,
            'digital_asset_kind' => $entitlement->digital_asset_kind,
            'product_id' => $entitlement->product_id,
            'product_version_id' => $entitlement->product_version_id,
            'product_file_id' => $entitlement->product_file_id,
            'wizard_project_id' => $entitlement->wizard_project_id,
            'custom_lut_build_id' => $entitlement->custom_lut_build_id,
            'custom_lut_build_file_id' => $entitlement->custom_lut_build_file_id,
            'item_display_name_snapshot' => $item?->displayName(),
            'item_version_snapshot' => $item?->versionLabel(),
            'status' => DownloadStatus::Started,
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
            'started_at' => now(),
            'size_bytes' => $package->sizeBytes,
        ]);

        $headers = [
            'Content-Type' => 'application/zip',
            'Cache-Control' => 'private, no-store, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ];

        if ($package->sizeBytes > 0) {
            $headers['Content-Length'] = (string) $package->sizeBytes;
        }

        $response = response()->streamDownload(function () use ($disk, $package, $event): void {
            $stream = $disk->readStream($package->path);

            if (! is_resource($stream)) {
                $this->markFailed($event);

                return;
            }

            $bytes = 0;

            try {
                while (! feof($stream)) {
                    $chunk = fread($stream, 1024 * 1024);

                    if ($chunk === false) {
                        $this->markFailed($event);

                        return;
                    }

                    $bytes += strlen($chunk);
                    echo $chunk;
                    flush();
                }

                $event->forceFill([
                    'status' => DownloadStatus::Completed,
                    'completed_at' => now(),
                    'size_bytes' => $bytes,
                ])->save();
            } finally {
                fclose($stream);
            }
        }, $package->downloadName, $headers);

        $response->setPrivate();
        $response->setMaxAge(0);
        $response->headers->addCacheControlDirective('no-store');
        $response->headers->set('Pragma', 'no-cache');

        return $response;
    }

    private function markFailed(DownloadEvent $event): void
    {
        $event->forceFill([
            'status' => DownloadStatus::Failed,
            'failed_at' => now(),
        ])->save();
    }
}
