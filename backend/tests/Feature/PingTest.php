<?php

namespace Tests\Feature;

use Tests\TestCase;

class PingTest extends TestCase
{
    public function test_ping_responde_ok(): void
    {
        $this->getJson('/api/ping')
            ->assertOk()
            ->assertExactJson(['status' => 'ok']);
    }
}
