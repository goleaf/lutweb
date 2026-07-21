<?php

namespace App\Http\Controllers\Account;

use App\Enums\DownloadStatus;
use App\Http\Controllers\Controller;
use App\Models\DownloadEvent;
use App\Models\Entitlement;
use App\Models\User;
use App\Services\Checkout\ResolveEntitlementPackage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EntitlementDownloadController extends Controller
{
    public function __construct(
        private readonly ResolveEntitlementPackage $packages,
    ) {}

    public function __invoke(Request $request, Entitlement $entitlement): StreamedResponse
    {
        /** @var User $user */
        $user = $request->user();
        $package = $this->packages->handle($entitlement, $user);
        $entitlement->loadMissing(['orderItem', 'order']);

        $event = DownloadEvent::query()->create([
            'entitlement_id' => $entitlement->id,
            'digital_asset_kind' => $entitlement->digital_asset_kind,
            'user_id' => $user->id,
            'order_id' => $entitlement->order_id,
            'product_id' => $entitlement->product_id,
            'product_version_id' => $entitlement->product_version_id,
            'product_file_id' => $entitlement->product_file_id,
            'wizard_project_id' => $entitlement->wizard_project_id,
            'custom_lut_build_id' => $entitlement->custom_lut_build_id,
            'custom_lut_build_file_id' => $entitlement->custom_lut_build_file_id,
            'item_display_name_snapshot' => $entitlement->orderItem->displayName(),
            'item_version_snapshot' => $entitlement->orderItem->versionLabel(),
            'status' => DownloadStatus::Started,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'started_at' => now(),
            'size_bytes' => $package->sizeBytes,
        ]);

        return response()->streamDownload(function () use ($package, $event): void {
            $stream = Storage::disk($package->disk)->readStream($package->path);

            if ($stream === false) {
                $event->forceFill([
                    'status' => DownloadStatus::Failed,
                    'failed_at' => now(),
                ])->save();

                return;
            }

            while (! feof($stream)) {
                echo fread($stream, 1024 * 1024);
            }

            if (is_resource($stream)) {
                fclose($stream);
            }

            $event->forceFill([
                'status' => DownloadStatus::Completed,
                'completed_at' => now(),
            ])->save();
        }, $package->downloadName, [
            'Content-Type' => 'application/zip',
            'Content-Length' => (string) $package->sizeBytes,
            'Cache-Control' => 'private, no-store, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
