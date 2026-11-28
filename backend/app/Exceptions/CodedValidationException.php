<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Enums\ErrorCodes;
use Illuminate\Validation\ValidationException;

/**
 * A validation refusal carrying a stable `error.code` instead of the generic
 * VALIDATION_ERROR, so a client can tell one refusal apart from the others
 * on the same field without reading the message. Rendered by the global
 * ValidationException handler (bootstrap/app.php), same body otherwise.
 */
class CodedValidationException extends ValidationException
{
    public ErrorCodes $errorCode = ErrorCodes::ValidationError;

    /**
     * @param  array<string, string|list<string>>  $messages
     */
    public static function withCode(ErrorCodes $code, array $messages): static
    {
        $exception = static::withMessages($messages);
        $exception->errorCode = $code;

        return $exception;
    }
}
