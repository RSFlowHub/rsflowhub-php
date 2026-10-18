<?php

declare(strict_types=1);

namespace RSFlowHub\Exceptions;

/** 401: the API key is missing, invalid or revoked (API_KEY_MISSING, INVALID_API_KEY, UNAUTHORIZED). */
class AuthenticationException extends ApiException
{
}
