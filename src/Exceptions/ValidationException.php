<?php

declare(strict_types=1);

namespace RSFlowHub\Exceptions;

/** 422: the request body failed validation (VALIDATION_ERROR). */
class ValidationException extends ApiException
{
    /**
     * Field => list of messages, e.g. ["text" => ["The text field is required."]].
     *
     * @return array<string, list<string>>
     */
    public function getErrors(): array
    {
        $errors = [];
        foreach ($this->getDetails() as $field => $messages) {
            $errors[(string) $field] = array_values(array_map('strval', (array) $messages));
        }

        return $errors;
    }
}
