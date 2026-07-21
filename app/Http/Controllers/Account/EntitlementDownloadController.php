<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Entitlement;
use App\Services\Downloads\StreamEntitlementDownload;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EntitlementDownloadController extends Controller
{
    public function __construct(
        private readonly StreamEntitlementDownload $downloads,
    ) {}

    public function download(Request $request, Entitlement $entitlement): StreamedResponse
    {
        $this->authorize('download', $entitlement);

        return $this->downloads->handle($request, $entitlement);
    }
}
