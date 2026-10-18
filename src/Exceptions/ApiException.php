<?php

declare(strict_types=1);

namespace RSFlowHub\Exceptions;

use Psr\Http\Message\ResponseInterface;

/**
 * The API answered with an error envelope:
 * {"success": false, "request_id": "...", "error": {"code", "message", "details"}}.
 *
 * Catch this to handle every API error; catch a subclass for one kind.
 */
class ApiException extends RSFlowHubException
{
    /**
     * @param array<string, mixed> $details
     * @param array<string, mixed> $body
     */
    public function __construct(
        string $message,
        private readonly int $status,
        private readonly string $errorCode,
        private readonly array $details = [],
        private readonly ?string $requestId = null,
        private readonly array $body = [],
        private readonly ?ResponseInterface $response = null,
    ) {
        parent::__construct($message, $status);
    }

    /**
     * Build the most specific exception for an error response.
     */
    public static function fromResponse(ResponseInterface $response, array $body): self
    {
        $status = $response->getStatusCode();
        $error = is_array($body['error'] ?? null) ? $body['error'] : [];
        $code = (string) ($error['code'] ?? self::codeForStatus($status));
        $message = (string) ($error['message'] ?? $body['message'] ?? ('HTTP ' . $status . ' ' . $response->getReasonPhrase()));
        $details = is_array($error['details'] ?? null) ? $error['details'] : [];
        $requestId = isset($body['request_id']) ? (string) $body['request_id'] : ($response->getHeaderLine('X-Request-Id') ?: null);

        $class = match (true) {
            in_array($code, ['API_KEY_MISSING', 'INVALID_API_KEY', 'UNAUTHORIZED'], true) || $status === 401 => AuthenticationException::class,
            $code === 'INSUFFICIENT_CREDITS' || $status === 402 => InsufficientCreditsException::class,
            in_array($code, ['SPENDING_LIMIT_REACHED', 'USER_INACTIVE'], true) || $status === 403 => PermissionDeniedException::class,
            $code === 'VALIDATION_ERROR' || $status === 422 => ValidationException::class,
            $code === 'RATE_LIMIT_EXCEEDED' || $status === 429 => RateLimitException::class,
            in_array($code, ['ENDPOINT_NOT_FOUND', 'NOT_FOUND'], true) || $status === 404 => NotFoundException::class,
            in_array($status, [502, 503, 504], true) => ServiceUnavailableException::class,
            $status >= 500 => ServerException::class,
            default => self::class,
        };

        return new $class($message, $status, $code, $details, $requestId, $body, $response);
    }

    private static function codeForStatus(int $status): string
    {
        return match ($status) {
            401 => 'UNAUTHORIZED',
            402 => 'INSUFFICIENT_CREDITS',
            404 => 'ENDPOINT_NOT_FOUND',
            405 => 'METHOD_NOT_ALLOWED',
            422 => 'VALIDATION_ERROR',
            429 => 'RATE_LIMIT_EXCEEDED',
            503 => 'PROVIDER_UNAVAILABLE',
            default => $status >= 500 ? 'INTERNAL_SERVER_ERROR' : 'HTTP_' . $status,
        };
    }

    /** HTTP status code, e.g. 422. */
    public function getStatus(): int
    {
        return $this->status;
    }

    /** Machine-readable error code, e.g. "VALIDATION_ERROR". */
    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    /** @return array<string, mixed> */
    public function getDetails(): array
    {
        return $this->details;
    }

    /** Server request id. Quote it when you contact support. */
    public function getRequestId(): ?string
    {
        return $this->requestId;
    }

    /** @return array<string, mixed> the decoded JSON body */
    public function getBody(): array
    {
        return $this->body;
    }

    public function getResponse(): ?ResponseInterface
    {
        return $this->response;
    }
}
