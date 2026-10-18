<?php

declare(strict_types=1);

namespace RSFlowHub\Exceptions;

/** The request never got an answer (DNS, TLS, timeout, connection refused). */
class ConnectionException extends RSFlowHubException
{
}
