<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Assigns a correlation id and a per-request id to every incoming request
 * (§0.30) so operational failures can be diagnosed without leaking financial
 * data. Both ids are added to the log context — Laravel appends context to
 * every log entry and propagates it to queued jobs — and echoed back as
 * response headers.
 *
 * Inbound values are honoured only when well-formed (bounded length and a safe
 * character set); anything else is replaced with a generated UUID so a client
 * cannot forge or inject header content.
 */
class AssignCorrelationId
{
    private const ID_PATTERN = '/^[A-Za-z0-9._-]{1,100}$/';

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $correlationId = $this->resolve($request, 'X-Correlation-Id');
        $requestId = $this->resolve($request, 'X-Request-Id');

        $request->attributes->set('correlation_id', $correlationId);
        $request->attributes->set('request_id', $requestId);

        Context::add('correlation_id', $correlationId);
        Context::add('request_id', $requestId);

        $response = $next($request);

        $response->headers->set('X-Correlation-Id', $correlationId);
        $response->headers->set('X-Request-Id', $requestId);

        return $response;
    }

    private function resolve(Request $request, string $header): string
    {
        $value = $request->headers->get($header);

        if (is_string($value) && preg_match(self::ID_PATTERN, $value) === 1) {
            return $value;
        }

        return (string) Str::uuid();
    }
}
