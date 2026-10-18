<?php

declare(strict_types=1);

namespace RSFlowHub;

use GuzzleHttp\ClientInterface;
use InvalidArgumentException;

/**
 * Client settings. Every option can also come from the environment:
 * RSFLOWHUB_API_KEY, RSFLOWHUB_BASE_URL.
 */
final class Options
{
    public const DEFAULT_BASE_URL = 'https://rsflowhub.com/api/v1';

    /**
     * @param array<string, string> $headers extra headers sent with every request
     * @param (callable(float): void)|null $sleep used between retries (tests pass a no-op)
     */
    public function __construct(
        public readonly string $apiKey,
        public readonly string $baseUrl = self::DEFAULT_BASE_URL,
        public readonly float $timeout = 60.0,
        public readonly int $maxRetries = 2,
        public readonly array $headers = [],
        public readonly ?ClientInterface $httpClient = null,
        public readonly bool $liveSpec = true,
        public readonly mixed $sleep = null,
    ) {
        if (trim($apiKey) === '') {
            throw new InvalidArgumentException(
                'No RSFlowHub API key. Pass it to new RSFlowHub(\'rfh_...\') or set RSFLOWHUB_API_KEY. '
                . 'Create one at https://rsflowhub.com/dashboard.'
            );
        }
        if ($maxRetries < 0) {
            throw new InvalidArgumentException('max_retries cannot be negative.');
        }
    }

    /**
     * @param array{
     *     api_key?: string, base_url?: string, timeout?: float|int, max_retries?: int,
     *     headers?: array<string, string>, http_client?: ClientInterface, live_spec?: bool,
     *     sleep?: callable(float): void
     * } $options
     */
    public static function from(?string $apiKey, array $options = []): self
    {
        $env = static fn (string $name): ?string => ($v = getenv($name)) !== false && $v !== '' ? $v : ($_ENV[$name] ?? null);

        return new self(
            apiKey: (string) ($apiKey ?? $options['api_key'] ?? $env('RSFLOWHUB_API_KEY') ?? ''),
            baseUrl: rtrim((string) ($options['base_url'] ?? $env('RSFLOWHUB_BASE_URL') ?? self::DEFAULT_BASE_URL), '/'),
            timeout: (float) ($options['timeout'] ?? 60.0),
            maxRetries: (int) ($options['max_retries'] ?? 2),
            headers: (array) ($options['headers'] ?? []),
            httpClient: $options['http_client'] ?? null,
            liveSpec: (bool) ($options['live_spec'] ?? true),
            sleep: $options['sleep'] ?? null,
        );
    }
}
