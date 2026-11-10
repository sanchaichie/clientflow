<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Send requests the way the Nuxt SPA does. Sanctum only starts a session
     * (needed for login/logout) for requests from SANCTUM_STATEFUL_DOMAINS.
     */
    protected function fromSpa(): static
    {
        $frontend = config('app.frontend_url');

        return $this->withHeaders([
            'Origin' => $frontend,
            'Referer' => $frontend.'/',
            'Accept' => 'application/json',
        ]);
    }
}
