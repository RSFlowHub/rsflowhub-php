<?php

declare(strict_types=1);

namespace RSFlowHub\Exceptions;

/** 502/503/504: the AI is temporarily unavailable (PROVIDER_UNAVAILABLE, AI_PROVIDER_ERROR). Retried automatically. */
class ServiceUnavailableException extends ApiException
{
}
