<?php

namespace App\Services\Checkout;

use App\Enums\CustomLutBuildFileKind;
use App\Enums\DigitalAssetKind;
use App\Enums\FulfillmentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ProductFileKind;
use App\Models\CustomLutBuildFile;
use App\Models\Entitlement;
use App\Models\ProductFile;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ResolveEntitlementPackage
{
    public function handle(Entitlement $entitlement, User $user): ResolvedEntitlementPackage
    {
        $entitlement->loadMissing(['order.payment', 'orderItem', 'productFile', 'customLutBuildFile']);

        if (! $entitlement->mayBeDownloadedBy($user)) {
            throw new NotFoundHttpException;
        }

        $order = $entitlement->order;

        if (
            $order === null
            || $entitlement->orderItem === null
            || $order->status !== OrderStatus::Completed
            || $order->fulfillment_status !== FulfillmentStatus::Ready
            || ! in_array($order->payment_status, [PaymentStatus::Completed, PaymentStatus::NotRequired], true)
        ) {
            throw new NotFoundHttpException;
        }

        if (
            $order->payment_status === PaymentStatus::Completed
            && ($order->payment === null || $order->payment->status !== PaymentStatus::Completed)
        ) {
            throw new NotFoundHttpException;
        }

        if ($entitlement->isCatalogProduct()) {
            return $this->catalogPackage($entitlement);
        }

        return $this->customLutPackage($entitlement);
    }

    private function catalogPackage(Entitlement $entitlement): ResolvedEntitlementPackage
    {
        $file = $entitlement->productFile;

        if (! $file instanceof ProductFile || $file->kind !== ProductFileKind::PackageZip || $file->disk !== 'private') {
            throw new NotFoundHttpException;
        }

        if (! $this->hasApprovedCatalogPrefix($file->path) || ! Storage::disk($file->disk)->exists($file->path)) {
            throw new NotFoundHttpException;
        }

        return new ResolvedEntitlementPackage(
            DigitalAssetKind::CatalogProduct,
            $file->disk,
            $file->path,
            $this->downloadName($entitlement->orderItem->product_slug.'-'.$entitlement->orderItem->product_version, 'zip'),
            $file->size_bytes,
        );
    }

    private function customLutPackage(Entitlement $entitlement): ResolvedEntitlementPackage
    {
        $file = $entitlement->customLutBuildFile;

        if (
            ! $file instanceof CustomLutBuildFile
            || $file->kind !== CustomLutBuildFileKind::PackageZip
            || $file->custom_lut_build_id !== $entitlement->custom_lut_build_id
            || $file->disk !== config('custom-lut-commerce.private_disk', 'private')
        ) {
            throw new NotFoundHttpException;
        }

        $prefix = trim((string) config('custom-lut-commerce.build_prefix'), '/').'/';

        if (! Str::startsWith($file->path, $prefix) || ! Storage::disk($file->disk)->exists($file->path)) {
            throw new NotFoundHttpException;
        }

        if ((bool) config('custom-lut-commerce.verify_package_metadata_on_download', true) && $file->size_bytes <= 0) {
            throw new NotFoundHttpException;
        }

        if ((bool) config('custom-lut-commerce.verify_package_hash_on_download', false)) {
            $this->assertCustomLutPackageHash($file);
        }

        return new ResolvedEntitlementPackage(
            DigitalAssetKind::CustomLutBuild,
            $file->disk,
            $file->path,
            $this->downloadName($entitlement->orderItem->product_slug, 'zip'),
            $file->size_bytes,
        );
    }

    private function assertCustomLutPackageHash(CustomLutBuildFile $file): void
    {
        if ($file->sha256 === null || ! preg_match('/\A[a-f0-9]{64}\z/i', $file->sha256)) {
            throw new NotFoundHttpException;
        }

        $stream = Storage::disk($file->disk)->readStream($file->path);

        if ($stream === null) {
            throw new NotFoundHttpException;
        }

        try {
            $context = hash_init('sha256');

            while (! feof($stream)) {
                $chunk = fread($stream, 1024 * 1024);

                if ($chunk === false) {
                    throw new NotFoundHttpException;
                }

                hash_update($context, $chunk);
            }

            $actualHash = hash_final($context);
        } finally {
            fclose($stream);
        }

        if (! hash_equals(strtolower($file->sha256), $actualHash)) {
            throw new NotFoundHttpException;
        }
    }

    private function downloadName(string $stem, string $extension): string
    {
        $safeStem = Str::slug($stem);

        if ($safeStem === '') {
            $safeStem = 'lut-package';
        }

        return $safeStem.'.'.$extension;
    }

    private function hasApprovedCatalogPrefix(string $path): bool
    {
        $normalizedPath = trim($path, '/');
        $prefixes = config('checkout.product_file_prefixes', ['catalog/product-files']);

        if (! is_array($prefixes)) {
            return false;
        }

        foreach ($prefixes as $prefix) {
            if (! is_string($prefix)) {
                continue;
            }

            $normalizedPrefix = trim($prefix, '/').'/';

            if (Str::startsWith($normalizedPath, $normalizedPrefix)) {
                return true;
            }
        }

        return false;
    }
}
