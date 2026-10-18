<?php

declare(strict_types=1);

namespace RSFlowHub;

use ArrayAccess;
use JsonSerializable;
use Psr\Http\Message\ResponseInterface;

/**
 * A successful API answer: {"success": true, "request_id", "data", "meta"}.
 *
 * Read the result with array access ($res['sentiment']), ->data, or get('a.b').
 *
 * @implements ArrayAccess<string, mixed>
 */
final class Response implements ArrayAccess, JsonSerializable
{
    /** @var array<string, mixed> */
    public readonly array $data;

    /** @var array<string, mixed> */
    public readonly array $meta;

    public readonly ?string $requestId;

    /**
     * @param array<string, mixed> $body the decoded JSON body
     */
    public function __construct(
        public readonly array $body,
        private readonly ResponseInterface $http,
    ) {
        $data = $body['data'] ?? [];
        $this->data = is_array($data) ? $data : ['value' => $data];
        $this->meta = is_array($body['meta'] ?? null) ? $body['meta'] : [];
        $this->requestId = isset($body['request_id'])
            ? (string) $body['request_id']
            : ($http->getHeaderLine('X-Request-Id') ?: null);
    }

    /**
     * Read a value from data with dot notation: get('items.0.title').
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->data;
        foreach (explode('.', $key) as $segment) {
            if (! is_array($value) || ! array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    /**
     * The AI output. AI endpoints answer {"data": {"result": {...}}}: result() returns
     * that inner object, result('sentiment') one key of it (dot notation works).
     * Responses without a "result" (knowledge bases, chatbots) return data itself.
     */
    public function result(?string $key = null, mixed $default = null): mixed
    {
        $base = array_key_exists('result', $this->data) ? 'result' : null;

        if ($key === null) {
            return $base ? $this->data['result'] : $this->data;
        }

        return $this->get($base ? "result.{$key}" : $key, $default);
    }

    public function status(): int
    {
        return $this->http->getStatusCode();
    }

    public function header(string $name): string
    {
        return $this->http->getHeaderLine($name);
    }

    /**
     * Your rate-limit state after this call, from the X-RateLimit-* headers.
     *
     * @return array{limit: ?int, remaining: ?int, reset: ?int}
     */
    public function rateLimit(): array
    {
        $int = fn (string $name): ?int => is_numeric($v = $this->header($name)) ? (int) $v : null;

        return [
            'limit' => $int('X-RateLimit-Limit'),
            'remaining' => $int('X-RateLimit-Remaining'),
            'reset' => $int('X-RateLimit-Reset'),
        ];
    }

    public function getHttpResponse(): ResponseInterface
    {
        return $this->http;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->data;
    }

    public function jsonSerialize(): array
    {
        return $this->body;
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->data[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->data[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new \LogicException('Responses are read-only.');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new \LogicException('Responses are read-only.');
    }

    public function __get(string $name): mixed
    {
        return $this->data[$name] ?? null;
    }

    public function __isset(string $name): bool
    {
        return isset($this->data[$name]);
    }
}
