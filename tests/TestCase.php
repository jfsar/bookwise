<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    /**
     * Confirm the authenticated user's password so requests pass the password.confirm middleware.
     */
    protected function confirmPassword(string $password = 'password'): void
    {
        $this->post('/user/confirm-password', ['password' => $password]);
    }
}
