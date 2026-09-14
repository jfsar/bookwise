<?php

namespace Tests\Feature;

use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Vite;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    public function test_security_headers_are_set_on_responses(): void
    {
        $response = $this->get('/');

        $response->assertOk()
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');
    }

    public function test_application_identity_and_deprecated_headers_are_not_leaked(): void
    {
        $response = $this->get('/');

        $response->assertOk()
            ->assertHeaderMissing('X-Powered-By')
            ->assertHeaderMissing('X-XSS-Protection');
    }

    public function test_content_security_policy_is_strict_on_scripts_and_allows_nonces(): void
    {
        $response = $this->get('/');

        $response->assertOk();

        $this->assertPolicyUsesTheMiddlewareNonce($response);
    }

    public function test_content_security_policy_allows_the_vite_dev_server_when_running_hot(): void
    {
        $fakeVite = new class extends Vite
        {
            public function isRunningHot(): bool
            {
                return true;
            }

            public function devServerUrl(): string
            {
                return 'http://localhost:5173';
            }
        };

        $this->swap(Vite::class, $fakeVite);

        $response = $this->handleMiddleware();

        $policy = $response->headers->get('Content-Security-Policy');
        $this->assertIsString($policy);
        $this->assertStringContainsString('http://localhost:5173', $policy);
        $this->assertStringContainsString('ws://localhost:5173', $policy);
        $this->assertStringContainsString("'nonce-".$fakeVite->cspNonce()."'", $policy);
    }

    private function handleMiddleware(): Response
    {
        $middleware = new SecurityHeaders;

        $response = new Response('ok');
        $middleware->handle(Request::create('/'), fn () => $response);

        return $response;
    }

    private function assertPolicyUsesTheMiddlewareNonce($response): void
    {
        $policy = $response->headers->get('Content-Security-Policy');
        $content = $response->getContent();

        $this->assertIsString($policy);
        $this->assertIsString($content);
        $this->assertMatchesRegularExpression('/script-src \'self\' \'nonce-(?<nonce>[A-Za-z0-9]+)\'/', $policy, $policy);
        $this->assertStringContainsString('object-src \'none\'', $policy);
        $this->assertStringContainsString('frame-ancestors \'self\'', $policy);
        $this->assertStringContainsString('style-src \'self\' \'unsafe-inline\'', $policy);

        preg_match('/script-src \'self\' \'nonce-(?<nonce>[A-Za-z0-9]+)\'/', $policy, $matches);
        $this->assertStringContainsString('nonce="'.$matches['nonce'].'"', $content);
    }
}
