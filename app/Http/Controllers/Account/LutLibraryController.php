<?php

namespace App\Http\Controllers\Account;

use App\Enums\EntitlementStatus;
use App\Http\Controllers\Controller;
use App\Models\Entitlement;
use App\Services\Downloads\StreamEntitlementDownload;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LutLibraryController extends Controller
{
    public function index(Request $request): Response
    {
        $entitlements = Entitlement::query()
            ->with(['order.item', 'product.coverMedia'])
            ->where('user_id', $request->user()->id)
            ->latest('granted_at')
            ->paginate(12)
            ->withQueryString();

        return Inertia::render('Account/Luts/Index', [
            'entitlements' => [
                'data' => $entitlements->getCollection()->map(fn (Entitlement $entitlement): array => $this->entitlementData($entitlement))->values(),
                'meta' => $entitlements->toArray(),
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
        $downloadable = $entitlement->status === EntitlementStatus::Active;

        return [
            'id' => $entitlement->id,
            'product_name' => $item?->product_name ?? 'Purchased LUT',
            'product_type' => $item?->product_type,
            'product_version' => $item?->product_version,
            'order_number' => $entitlement->order?->number,
            'purchase_date' => $entitlement->granted_at?->toISOString(),
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
}
