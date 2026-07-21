<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AddSecurityHeaders
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ((bool) config('security.csp_enabled', true)) {
            $nonce = base64_encode(random_bytes(16));
            $request->attributes->set('csp_nonce', $nonce);
            Vite::useCspNonce($nonce);
        }

        $response = $next($request);

        if (! (bool) config('security.headers_enabled', true)) {
            return $response;
        }

        $headers = $response->headers;
        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('X-Frame-Options', 'DENY');
        $headers->set('Cross-Origin-Opener-Policy', 'same-origin-allow-popups');
        $headers->set('Cross-Origin-Resource-Policy', 'same-site');
        $headers->set('Permissions-Policy', 'accelerometer=(), ambient-light-sensor=(), camera=(), geolocation=(), gyroscope=(), magnetometer=(), microphone=(), payment=(), usb=()');

        if ((bool) config('security.csp_enabled', true)) {
            $headers->set($this->cspHeaderName($request), $this->policy($request));
        }

        if ((bool) config('security.hsts_enabled', false) && $request->isSecure()) {
            $hsts = 'max-age='.(int) config('security.hsts_max_age', 31_536_000);

            if ((bool) config('security.hsts_include_subdomains', false)) {
                $hsts .= '; includeSubDomains';
            }

            if ((bool) config('security.hsts_preload', false)) {
                $hsts .= '; preload';
            }

            $headers->set('Strict-Transport-Security', $hsts);
        }

        if ($this->isSensitiveResponse($request)) {
            $headers->set('Cache-Control', 'max-age=0, no-store, private');
            $headers->set('Pragma', 'no-cache');
        }

        return $response;
    }

    private function cspHeaderName(Request $request): string
    {
        if ($request->is('admin') || $request->is('admin/*') || (bool) config('security.csp_report_only', true)) {
            return 'Content-Security-Policy-Report-Only';
        }

        return 'Content-Security-Policy';
    }

    private function policy(Request $request): string
    {
        $nonce = (string) $request->attributes->get('csp_nonce', '');
        $script = ["'self'"];
        $style = ["'self'", "'unsafe-inline'"];
        $img = ["'self'", 'data:', 'blob:'];
        $connect = ["'self'"];
        $frame = ["'none'"];
        $form = ["'self'"];

        if ($nonce !== '') {
            $script[] = "'nonce-{$nonce}'";
        }

        foreach ($this->configuredHosts('allowed_public_asset_hosts') as $host) {
            $img[] = $host;
        }

        if ($this->isCheckoutRoute($request)) {
            foreach ($this->configuredHosts('paypal_browser_hosts') as $host) {
                $script[] = $host;
                $connect[] = $host;
                $frame[] = $host;
                $img[] = $host;
                $form[] = $host;
            }
        }

        if (app()->isLocal()) {
            $script[] = 'http://localhost:*';
            $script[] = 'http://127.0.0.1:*';
            $connect[] = 'ws://localhost:*';
            $connect[] = 'ws://127.0.0.1:*';
        }

        $directives = [
            'default-src' => ["'self'"],
            'base-uri' => ["'self'"],
            'object-src' => ["'none'"],
            'frame-ancestors' => ["'none'"],
            'form-action' => array_values(array_unique($form)),
            'script-src' => array_values(array_unique($script)),
            'style-src' => array_values(array_unique($style)),
            'img-src' => array_values(array_unique($img)),
            'font-src' => ["'self'", 'data:'],
            'connect-src' => array_values(array_unique($connect)),
            'worker-src' => ["'self'", 'blob:'],
            'media-src' => ["'none'"],
            'manifest-src' => ["'self'"],
        ];

        return collect($directives)
            ->map(fn (array $values, string $directive): string => $directive.' '.implode(' ', $values))
            ->implode('; ');
    }

    /**
     * @return list<string>
     */
    private function configuredHosts(string $key): array
    {
        $configuredHosts = config('security.'.$key, []);

        if (! is_array($configuredHosts)) {
            return [];
        }

        $hosts = [];

        foreach ($configuredHosts as $host) {
            if (is_string($host) && $host !== '' && ! Str::contains($host, ['*', "\n", "\r"])) {
                $hosts[] = $host;
            }
        }

        return $hosts;
    }

    private function isCheckoutRoute(Request $request): bool
    {
        return $request->routeIs('checkout.*') || $request->routeIs('custom-lut.checkout.*');
    }

    private function isSensitiveResponse(Request $request): bool
    {
        return $request->is('account') || $request->is('account/*') || $request->is('checkout/*') || $request->routeIs('checkout.*') || $request->hasValidSignature();
    }
}
