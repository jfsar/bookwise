<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveClientIp
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->server->get('REMOTE_ADDR') === '127.0.0.100') {
            $forwarded = $request->headers->get('Forwarded');
            if ($forwarded) {
                preg_match('/for="?(\[?[^";]+?\]?)"/', $forwarded, $m);
                if ($m) {
                    $ip = trim($m[1], '[]"');
                    // Unmap IPv4-in-IPv6
                    if (str_starts_with($ip, '::ffff:')) {
                        $ip = substr($ip, 7);
                    }
                    $request->server->set('REMOTE_ADDR', $ip);
                }
            }
        }

        return $next($request);
    }
}
