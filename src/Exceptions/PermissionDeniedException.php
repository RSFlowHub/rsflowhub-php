<?php

declare(strict_types=1);

namespace RSFlowHub\Exceptions;

/** 403: spending limit reached or account inactive (SPENDING_LIMIT_REACHED, USER_INACTIVE). */
class PermissionDeniedException extends ApiException
{
}
