<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_up_returns_ok(): void
    {
        $this->get('/up')->assertOk();
    }

    public function test_login_page_is_reachable(): void
    {
        $this->superAdmin();

        $this->get('/login')->assertOk();
    }
}
