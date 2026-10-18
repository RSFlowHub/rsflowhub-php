<?php

declare(strict_types=1);

namespace RSFlowHub\Exceptions;

/** 402: your balance cannot cover this call (INSUFFICIENT_CREDITS). Nothing was charged. */
class InsufficientCreditsException extends ApiException
{
    public function getRequiredCredits(): ?int
    {
        $value = $this->getDetails()['required_credits'] ?? null;

        return is_numeric($value) ? (int) $value : null;
    }

    public function getAvailableCredits(): ?int
    {
        $value = $this->getDetails()['available_credits'] ?? null;

        return is_numeric($value) ? (int) $value : null;
    }
}
