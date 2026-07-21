<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AddRequestId
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $header = (string) config('security.request_id_header', 'X-Request-ID');
        $incoming = (string) $request->headers->get($header, '');
        $requestId = $this->isSafeRequestId($incoming) ? $incoming : (string) Str::uuid();

        $request->attributes->set('request_id', $requestId);
        Log::withContext(['request_id' => $requestId]);

        $response = $next($request);
        $response->headers->set($header, $requestId);

        return $response;
    }

    private function isSafeRequestId(string $value): bool
    {
        if ($value === '' || strlen($value) > 64 || preg_match('/[[:cntrl:]]/', $value) === 1) {
            return false;
        }

        return Str::isUuid($value) || preg_match('/^[0123456789ABCDEFGHJKMNPQRSTVWXYZ]{26}$/', $value) === 1;
    }
}
