<?php

namespace Tests\Feature;

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
}
