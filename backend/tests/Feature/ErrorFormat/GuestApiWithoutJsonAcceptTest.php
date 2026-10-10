<?php

declare(strict_types=1);

namespace Tests\Feature\ErrorFormat;

use Tests\TestCase;

class GuestApiWithoutJsonAcceptTest extends TestCase
{
    public function test_protected_api_route_returns_401_json_even_without_accept_header(): void
    {
        // Sans « Accept: application/json », Laravel tentait une redirection vers la route
        // `login` (inexistante dans cette API) et répondait 500 « Route [login] not defined ».
        $response = $this->get('/api/v1/user');

        $response->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');
    }
}
