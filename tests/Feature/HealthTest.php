<?php

namespace Tests\Feature;

use Tests\TestCase;

class HealthTest extends TestCase
{
    public function test_root_endpoint_returns_ok(): void
    {
        $response = $this->getJson('/');

        $response
            ->assertOk()
            ->assertJson([
                'status' => 'ok',
            ]);
    }
}
