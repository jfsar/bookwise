<?php

namespace Tests\Feature;

use App\Http\Middleware\ResolveClientIp;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class ResolveClientIpTest extends TestCase
{
    public function test_resolves_client_ip_from_forwarded_header(): void
    {
        $request = $this->proxyRequest('for="192.0.2.10"');

        $this->handleMiddleware($request);

        $this->assertSame('192.0.2.10', $request->server->get('REMOTE_ADDR'));
    }

    public function test_unmaps_ipv4_in_ipv6_addresses(): void
    {
        $request = $this->proxyRequest('for="::ffff:203.0.113.9"');

        $this->handleMiddleware($request);

        $this->assertSame('203.0.113.9', $request->server->get('REMOTE_ADDR'));
    }

    public function test_keeps_remote_addr_untouched_when_not_connecting_through_proxy(): void
    {
        $request = Request::create('/', 'GET', [], [], [], [
            'REMOTE_ADDR' => '198.51.100.7',
            'HTTP_FORWARDED' => 'for="192.0.2.99"',
        ]);

        $this->handleMiddleware($request);

        $this->assertSame('198.51.100.7', $request->server->get('REMOTE_ADDR'));
    }

    public function test_keeps_proxy_addr_when_forwarded_header_is_missing(): void
    {
        $request = $this->proxyRequest(null);

        $this->handleMiddleware($request);

        $this->assertSame('127.0.0.100', $request->server->get('REMOTE_ADDR'));
    }

    private function proxyRequest(?string $forwarded): Request
    {
        $server = ['REMOTE_ADDR' => '127.0.0.100'];

        if ($forwarded !== null) {
            $server['HTTP_FORWARDED'] = $forwarded;
        }

        return Request::create('/', 'GET', [], [], [], $server);
    }

    private function handleMiddleware(Request $request): Response
    {
        $middleware = new ResolveClientIp;

        $response = new Response('ok');

        return $middleware->handle($request, fn () => $response);
    }
}
