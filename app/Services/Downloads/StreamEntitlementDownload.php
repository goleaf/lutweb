<?php

namespace App\Services\Downloads;

use App\Enums\DownloadStatus;
use App\Enums\FulfillmentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ProductFileKind;
use App\Models\DownloadEvent;
use App\Models\Entitlement;
use App\Models\ProductFile;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StreamEntitlementDownload
{
    public function handle(Request $request, Entitlement $entitlement): StreamedResponse
    {
        $user = $request->user();

        if (! $user instanceof User || ! $this->mayDownload($user, $entitlement)) {
            abort(404);
        }

        $entitlement->loadMissing(['order.payment', 'order.item', 'productFile']);
        $file = $entitlement->productFile;

        if (! $file instanceof ProductFile || ! $this->fileIsAllowed($file)) {
            abort(404);
        }

        $disk = Storage::disk('private');

        if (! $disk->exists($file->path)) {
            abort(404);
        }

        $event = DownloadEvent::query()->create([
            'entitlement_id' => $entitlement->id,
            'user_id' => $user->id,
            'order_id' => $entitlement->order_id,
            'product_id' => $entitlement->product_id,
            'product_version_id' => $entitlement->product_version_id,
            'product_file_id' => $entitlement->product_file_id,
            'status' => DownloadStatus::Started,
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
            'started_at' => now(),
            'size_bytes' => $file->size_bytes,
        ]);

        $filename = $this->filename($entitlement);
        $headers = [
            'Content-Type' => 'application/zip',
            'Cache-Control' => 'private, no-store, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ];

        if ($file->size_bytes !== null) {
            $headers['Content-Length'] = (string) $file->size_bytes;
        }

        return response()->streamDownload(function () use ($disk, $file, $event): void {
            $stream = $disk->readStream($file->path);

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
        }, $filename, $headers);
    }

    private function mayDownload(User $user, Entitlement $entitlement): bool
    {
        $entitlement->loadMissing(['order.payment']);
        $order = $entitlement->order;
        $payment = $order?->payment;

        return $entitlement->mayBeDownloadedBy($user)
            && $order !== null
            && $order->status === OrderStatus::Completed
            && $order->fulfillment_status === FulfillmentStatus::Ready
            && in_array($order->payment_status, [PaymentStatus::Completed, PaymentStatus::NotRequired], true)
            && ($payment === null || in_array($payment->status, [PaymentStatus::Completed, PaymentStatus::NotRequired], true));
    }

    private function fileIsAllowed(ProductFile $file): bool
    {
        return $file->kind === ProductFileKind::PackageZip
            && $file->disk === 'private'
            && collect(config('checkout.product_file_prefixes', ['catalog/product-files']))
                ->contains(fn (string $prefix): bool => str_starts_with($file->path, trim($prefix, '/').'/'));
    }

    private function filename(Entitlement $entitlement): string
    {
        $item = $entitlement->order?->item;
        $slug = Str::slug($item?->product_slug ?: $item?->product_name ?: 'lut-package');
        $version = Str::slug($item?->product_version ?: 'v1');

        return trim($slug.'-'.$version, '-').'.zip';
    }

    private function markFailed(DownloadEvent $event): void
    {
        $event->forceFill([
            'status' => DownloadStatus::Failed,
            'failed_at' => now(),
        ])->save();
    }
}
