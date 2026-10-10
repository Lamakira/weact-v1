<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Shared 403 body for the registration kill switch (config('app.registration_enabled')).
 *
 * Consumers: RegisterFaceRequest, RegisterProducerRequest,
 * CompleteGoogleRegistrationRequest. The frontend matches on
 * `error.code === 'registration_disabled'` to render the "inscriptions suspendues"
 * panel, so the code string must stay identical across all three.
 */
trait RejectsWhenRegistrationDisabled
{
    /**
     * @throws \Illuminate\Http\Exceptions\HttpResponseException
     */
    protected function failedAuthorization(): void
    {
        throw new HttpResponseException(
            response()->json([
                'error' => [
                    'code' => 'registration_disabled',
                    'message' => 'Les inscriptions sont temporairement suspendues. Veuillez réessayer ultérieurement.',
                ],
            ], 403)
        );
    }
}
