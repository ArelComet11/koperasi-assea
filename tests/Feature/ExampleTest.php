<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MembuatData;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase, MembuatData;

    public function test_the_application_returns_a_successful_response(): void
    {
        $this->seedDasar();

        $response = $this->get('/');

        $response->assertStatus(200);
    }
}
