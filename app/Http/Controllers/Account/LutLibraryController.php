<?php

namespace App\Http\Controllers\Account;

use App\Enums\DigitalAssetKind;
use App\Enums\EntitlementStatus;
use App\Enums\FulfillmentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ProductFileKind;
use App\Http\Controllers\Controller;
use App\Models\Entitlement;
use App\Models\ProductFile;
use App\Services\Downloads\StreamEntitlementDownload;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LutLibraryController extends Controller
{
    public function index(Request $request): Response
    {
        $entitlements = Entitlement::query()
            ->with(['order.item', 'order.payment', 'product.coverMedia', 'productFile'])
            ->where('user_id', $request->user()->id)
            ->where('digital_asset_kind', DigitalAssetKind::CatalogProduct->value)
            ->latest('granted_at')
            ->paginate(12)
            ->withQueryString();

        return Inertia::render('Account/Luts/Index', [
            'entitlements' => [
                'data' => $entitlements->getCollection()->map(fn (Entitlement $entitlement): array => $this->entitlementData($entitlement))->values(),
                'meta' => Arr::except($entitlements->toArray(), ['data']),
            ],
        ]);
    }

    public function download(Request $request, Entitlement $entitlement, StreamEntitlementDownload $downloads): StreamedResponse
    {
        $this->authorize('download', $entitlement);

        return $downloads->handle($request, $entitlement);
    }

    /**
     * @return array<string, mixed>
     */
    private function entitlementData(Entitlement $entitlement): array
    {
        $item = $entitlement->order?->item;
        $cover = $entitlement->product?->coverMedia;
        $downloadable = $this->mayShowDownloadAction($entitlement);

        return [
            'id' => $entitlement->id,
            'product_name' => $item->product_name ?? 'Purchased LUT',
            'product_type' => $item?->product_type,
            'product_version' => $item?->product_version,
            'order_number' => $entitlement->order?->number,
            'purchase_date' => $entitlement->granted_at->toISOString(),
            'status' => $entitlement->status->value,
            'status_label' => $entitlement->status->label(),
            'cover' => $cover ? [
                'url' => Storage::disk('public')->url($cover->path),
                'alt_text' => $cover->alt_text,
            ] : null,
            'download_url' => $downloadable ? route('account.luts.download', $entitlement) : null,
            'message' => $downloadable ? null : 'This entitlement is not currently downloadable.',
        ];
    }

    private function mayShowDownloadAction(Entitlement $entitlement): bool
    {
        $order = $entitlement->order;
        $payment = $order?->payment;
        $file = $entitlement->productFile;

        if (
            $entitlement->status !== EntitlementStatus::Active
            || $order === null
            || $order->status !== OrderStatus::Completed
            || $order->fulfillment_status !== FulfillmentStatus::Ready
            || ! in_array($order->payment_status, [PaymentStatus::Completed, PaymentStatus::NotRequired], true)
        ) {
            return false;
        }

        if ($order->payment_status === PaymentStatus::Completed && $payment?->status !== PaymentStatus::Completed) {
            return false;
        }

        return $file instanceof ProductFile
            && $file->kind === ProductFileKind::PackageZip
            && $file->disk === 'private'
            && $this->hasApprovedCatalogPrefix($file->path)
            && Storage::disk('private')->exists($file->path);
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

            if (Str::startsWith($normalizedPath, trim($prefix, '/').'/')) {
                return true;
            }
        }

        return false;
    }
}
