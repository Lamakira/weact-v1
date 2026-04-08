<?php

namespace Tests;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Mirror production API auth in tests: actingAs() also provides a bearer token
     * for routes protected by auth:sanctum + api.token.
     */
    public function actingAs(Authenticatable $user, $guard = null): static
    {
        parent::actingAs($user, $guard);

        if (method_exists($user, 'createToken')) {
            $this->withToken($user->createToken('test-token')->plainTextToken);
        }

        return $this;
    }
}
