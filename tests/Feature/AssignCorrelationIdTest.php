<?php

namespace Tests\Feature;

use App\Http\Middleware\AssignCorrelationId;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class AssignCorrelationIdTest extends TestCase
{
    public function test_generates_ids_when_headers_are_absent(): void
    {
        $request = Request::create('/');

        $response = $this->handle($request);

        $correlationId = $request->attributes->get('correlation_id');
        $requestId = $request->attributes->get('request_id');

        $this->assertTrue(Str::isUuid($correlationId));
        $this->assertTrue(Str::isUuid($requestId));
        $this->assertNotSame($correlationId, $requestId);
        $this->assertSame($correlationId, $response->headers->get('X-Correlation-Id'));
        $this->assertSame($requestId, $response->headers->get('X-Request-Id'));
        $this->assertSame($correlationId, Context::get('correlation_id'));
        $this->assertSame($requestId, Context::get('request_id'));
    }

    public function test_honours_well_formed_inbound_ids(): void
    {
        $request = Request::create('/', 'GET', [], [], [], [
            'HTTP_X_CORRELATION_ID' => 'support-case_123',
            'HTTP_X_REQUEST_ID' => 'req.abc-9',
        ]);

        $response = $this->handle($request);

        $this->assertSame('support-case_123', $response->headers->get('X-Correlation-Id'));
        $this->assertSame('req.abc-9', $response->headers->get('X-Request-Id'));
        $this->assertSame('support-case_123', Context::get('correlation_id'));
        $this->assertSame('req.abc-9', Context::get('request_id'));
    }

    public function test_replaces_malformed_inbound_ids_with_generated_uuids(): void
    {
        $request = Request::create('/', 'GET', [], [], [], [
            'HTTP_X_CORRELATION_ID' => 'has spaces and <script>',
            'HTTP_X_REQUEST_ID' => str_repeat('a', 101),
        ]);

        $response = $this->handle($request);

        $this->assertTrue(Str::isUuid($response->headers->get('X-Correlation-Id')));
        $this->assertTrue(Str::isUuid($response->headers->get('X-Request-Id')));
    }

    public function test_response_carries_correlation_headers_over_http(): void
    {
        $response = $this->get('/', ['X-Correlation-Id' => 'trace-xyz']);

        $response->assertOk();
        $response->assertHeader('X-Correlation-Id', 'trace-xyz');
        $this->assertTrue(Str::isUuid($response->headers->get('X-Request-Id')));
    }

    private function handle(Request $request): Response
    {
        return (new AssignCorrelationId)->handle($request, fn () => new Response('ok'));
    }
}
