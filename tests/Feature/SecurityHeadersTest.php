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

    public function test_rendered_page_includes_the_csp_nonce(): void
    {
        $response = $this->get('/');

        $response->assertOk();

        $this->assertPolicyUsesTheMiddlewareNonce($response);
    }

    public function test_content_security_policy_is_strict_on_scripts_and_styles(): void
    {
        $this->swapVite(isHot: false);

        $policy = $this->handleMiddleware()->headers->get('Content-Security-Policy');

        $this->assertIsString($policy);
        $this->assertMatchesRegularExpression('/script-src \'self\' \'nonce-[A-Za-z0-9]+\'/', $policy);
        $this->assertMatchesRegularExpression('/style-src \'self\' \'nonce-[A-Za-z0-9]+\'/', $policy);
        $this->assertStringNotContainsString('style-src \'self\' \'unsafe-inline\'', $policy);
        $this->assertStringContainsString('object-src \'none\'', $policy);
        $this->assertStringContainsString('frame-ancestors \'self\'', $policy);
    }

    public function test_content_security_policy_upgrades_insecure_requests_outside_local_development(): void
    {
        $this->swapVite(isHot: false);

        $policy = $this->handleMiddleware()->headers->get('Content-Security-Policy');

        $this->assertIsString($policy);
        $this->assertStringContainsString('upgrade-insecure-requests', $policy);
        $this->assertStringNotContainsString('://localhost:5173', $policy);
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
        $this->assertStringNotContainsString('style-src \'self\' \'unsafe-inline\'', $policy);
        $this->assertStringNotContainsString('upgrade-insecure-requests', $policy);
    }

    private function swapVite(bool $isHot): void
    {
        $vite = new class($isHot) extends Vite
        {
            public function __construct(private readonly bool $isHot) {}

            public function isRunningHot(): bool
            {
                return $this->isHot;
            }
        };

        $this->swap(Vite::class, $vite);
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

        preg_match('/script-src \'self\' \'nonce-(?<nonce>[A-Za-z0-9]+)\'/', $policy, $matches);
        $this->assertStringContainsString('nonce="'.$matches['nonce'].'"', $content);
    }
}
