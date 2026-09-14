<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        header_remove('X-Powered-By');

        Vite::useCspNonce();

        $response = $next($request);

        $response->headers->set('Content-Security-Policy', $this->contentSecurityPolicy());
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');

        return $response;
    }

    /**
     * Build the Content Security Policy, allowing the Vite dev server when running hot.
     */
    private function contentSecurityPolicy(): string
    {
        $viteSources = [];

        if (Vite::isRunningHot()) {
            $origin = rtrim(Vite::devServerUrl(), '/');
            $websocket = preg_replace('/^http/i', 'ws', $origin);
            $viteSources = [
                'script' => $origin,
                'style' => $origin,
                'font' => $origin,
                'connect' => "$origin $websocket",
            ];
        }

        $script = "'self' 'nonce-".Vite::cspNonce()."'".(isset($viteSources['script']) ? ' '.$viteSources['script'] : '');
        $style = "'self' 'nonce-".Vite::cspNonce()."'".(isset($viteSources['style']) ? ' '.$viteSources['style'] : '');
        $font = "'self'".(isset($viteSources['font']) ? ' '.$viteSources['font'] : '');
        $connect = "'self'".(isset($viteSources['connect']) ? ' '.$viteSources['connect'] : '');

        $directives = [
            "default-src 'self'",
            "script-src $script",
            "style-src $style",
            "img-src 'self' data:",
            "font-src $font",
            "connect-src $connect",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'self'",
        ];

        if (! app()->isLocal() && ! Vite::isRunningHot()) {
            array_push($directives, 'upgrade-insecure-requests');
        }

        return implode('; ', $directives);
    }
}
