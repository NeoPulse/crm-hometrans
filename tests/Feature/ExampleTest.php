<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        // Anonymous users should be redirected to login because all areas require authentication.
        $response = $this->get('/');

        $response->assertRedirect('/login');
    }
}
