<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_application_returns_a_successful_response(): void
    {
        $this->admin();

        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->admin();

        $response = $this->get('/');

        $response->assertRedirect('/login');
    }
}
