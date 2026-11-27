<?php

declare(strict_types=1);

namespace Siberfx\PloiLaravel\Exceptions;

use Ploi\Exceptions\Http\NotValid;

/**
 * Ploi rejected the request data (422).
 */
class ValidationException extends NotValid implements PloiException
{
    use InteractsWithResponse;

    /**
     * The validation errors, keyed by field.
     *
     * @return array<string, array<int, string>>
     */
    public function errors(): array
    {
        $errors = $this->response->json('errors');

        return is_array($errors) ? $errors : [];
    }
}
