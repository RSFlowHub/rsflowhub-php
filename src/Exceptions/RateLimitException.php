<?php

declare(strict_types=1);

namespace RSFlowHub\Exceptions;

/** 429: too many requests (RATE_LIMIT_EXCEEDED). Retried automatically up to max_retries. */
class RateLimitException extends ApiException
{
    /** Seconds to wait before the next call, from the Retry-After header. */
    public function getRetryAfter(): ?int
    {
        $value = $this->getResponse()?->getHeaderLine('Retry-After');

        return is_numeric($value) ? (int) $value : null;
    }
}
