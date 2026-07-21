<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class EnforceTrustedHosts
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! app()->isProduction()) {
            return $next($request);
        }

        $host = Str::lower($request->getHost());
        $configuredHosts = config('security.trusted_hosts', []);
        $allowedHosts = [];

        if (is_array($configuredHosts)) {
            foreach ($configuredHosts as $configuredHost) {
                if (is_string($configuredHost) && $configuredHost !== '') {
                    $allowedHosts[] = Str::lower($configuredHost);
                }
            }
        }

        if ($allowedHosts === [] || preg_match('/^[a-z0-9.-]+$/', $host) !== 1 || ! in_array($host, $allowedHosts, true)) {
            abort(Response::HTTP_BAD_REQUEST);
        }

        return $next($request);
    }
}
