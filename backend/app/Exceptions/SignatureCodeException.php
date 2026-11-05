<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Enums\ErrorCodes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Échec d'un code de signature : rendue en enveloppe d'erreur standard avec le statut HTTP voulu.
 */
class SignatureCodeException extends \RuntimeException
{
    public function __construct(
        public readonly ErrorCodes $errorCode,
        string $message,
        public readonly int $status = 422,
        public readonly ?int $retryAfter = null,
    ) {
        parent::__construct($message);
    }

    public function render(Request $request): JsonResponse
    {
        $response = response()->json(
            $this->errorCode->envelope($this->getMessage(), $this->retryAfter !== null ? ['retry_after' => $this->retryAfter] : []),
            $this->status,
        );

        if ($this->retryAfter !== null) {
            $response->header('Retry-After', (string) $this->retryAfter);
        }

        return $response;
    }
}
