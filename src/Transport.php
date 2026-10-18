<?php

declare(strict_types=1);

namespace RSFlowHub;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use Psr\Http\Message\ResponseInterface;
use RSFlowHub\Exceptions\ApiException;
use RSFlowHub\Exceptions\ConnectionException;
use RSFlowHub\Exceptions\RSFlowHubException;
use SplFileInfo;

/**
 * Sends requests to the RSFlowHub API and turns answers into Response objects
 * or typed exceptions. Retries 429 / 502 / 503 / 504 and connection failures
 * with backoff (honouring Retry-After); those answers never charge credits.
 *
 * @internal use RSFlowHub::request() instead
 */
final class Transport
{
    private const RETRY_STATUSES = [429, 502, 503, 504];

    private ClientInterface $http;

    /** @var callable(float): void */
    private $sleep;

    public function __construct(private readonly Options $options)
    {
        $this->http = $options->httpClient ?? new GuzzleClient([
            RequestOptions::TIMEOUT => $options->timeout,
            RequestOptions::CONNECT_TIMEOUT => min(10.0, $options->timeout),
        ]);
        $this->sleep = $options->sleep ?? static fn (float $seconds) => usleep((int) ($seconds * 1_000_000));
    }

    /**
     * @param array<string, mixed> $body  JSON body (or multipart when it holds a file)
     * @param array<string, mixed> $query query string
     */
    public function send(string $method, string $path, array $body = [], array $query = []): Response
    {
        $method = strtoupper($method);
        $url = rtrim($this->options->baseUrl, '/') . '/' . ltrim($path, '/');

        $request = [
            RequestOptions::HTTP_ERRORS => false,
            RequestOptions::HEADERS => [
                'X-API-Key' => $this->options->apiKey,
                'Accept' => 'application/json',
                'User-Agent' => 'rsflowhub-php/' . RSFlowHub::VERSION . ' PHP/' . PHP_VERSION,
            ] + $this->options->headers,
        ];
        if ($query) {
            $request[RequestOptions::QUERY] = $query;
        }
        if ($body && $method !== 'GET') {
            if (self::hasFile($body)) {
                $request[RequestOptions::MULTIPART] = self::multipart($body);
            } else {
                $request[RequestOptions::JSON] = $body;
            }
        }

        $attempt = 0;
        while (true) {
            try {
                $response = $this->http->request($method, $url, $request);
            } catch (ConnectException $e) {
                if ($attempt < $this->options->maxRetries) {
                    ($this->sleep)($this->backoff($attempt++, null));
                    self::rewind($request);
                    continue;
                }
                throw new ConnectionException('Could not reach RSFlowHub: ' . $e->getMessage(), 0, $e);
            } catch (GuzzleException $e) {
                throw new ConnectionException('Request to RSFlowHub failed: ' . $e->getMessage(), 0, $e);
            }

            $status = $response->getStatusCode();
            if (in_array($status, self::RETRY_STATUSES, true) && $attempt < $this->options->maxRetries) {
                ($this->sleep)($this->backoff($attempt++, $response));
                self::rewind($request);
                continue;
            }

            return $this->toResponse($response);
        }
    }

    private function toResponse(ResponseInterface $response): Response
    {
        $raw = (string) $response->getBody();
        $body = $raw === '' ? [] : json_decode($raw, true);

        if (! is_array($body)) {
            if ($response->getStatusCode() >= 400) {
                throw ApiException::fromResponse($response, []);
            }
            throw new RSFlowHubException('RSFlowHub returned a non-JSON response (HTTP ' . $response->getStatusCode() . ').');
        }

        if ($response->getStatusCode() >= 400 || ($body['success'] ?? true) === false) {
            throw ApiException::fromResponse($response, $body);
        }

        return new Response($body, $response);
    }

    /** Seconds to wait: Retry-After when given (capped at 60), else 0.5s, 1s, 2s ... plus jitter. */
    private function backoff(int $attempt, ?ResponseInterface $response): float
    {
        $retryAfter = $response?->getHeaderLine('Retry-After');
        if (is_numeric($retryAfter)) {
            return min(60.0, max(0.0, (float) $retryAfter));
        }

        return min(8.0, 0.5 * (2 ** $attempt)) + mt_rand(0, 250) / 1000;
    }

    /** @param array<string, mixed> $body */
    private static function hasFile(array $body): bool
    {
        foreach ($body as $value) {
            if (is_resource($value) || $value instanceof SplFileInfo || $value instanceof \Psr\Http\Message\StreamInterface) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $body
     * @return list<array<string, mixed>>
     */
    private static function multipart(array $body, string $prefix = ''): array
    {
        $parts = [];
        foreach ($body as $key => $value) {
            $name = $prefix === '' ? (string) $key : $prefix . '[' . $key . ']';
            if ($value instanceof SplFileInfo) {
                $handle = fopen($value->getPathname(), 'rb');
                if ($handle === false) {
                    throw new RSFlowHubException('Cannot open file ' . $value->getPathname());
                }
                $parts[] = ['name' => $name, 'contents' => $handle, 'filename' => $value->getFilename()];
            } elseif (is_resource($value) || $value instanceof \Psr\Http\Message\StreamInterface) {
                $parts[] = ['name' => $name, 'contents' => $value];
            } elseif (is_array($value)) {
                // name[key]=value, the form encoding Laravel's "array" rule reads.
                array_push($parts, ...self::multipart($value, $name));
            } elseif (is_bool($value)) {
                $parts[] = ['name' => $name, 'contents' => $value ? '1' : '0'];
            } elseif ($value !== null) {
                $parts[] = ['name' => $name, 'contents' => (string) $value];
            }
        }

        return $parts;
    }

    /** File uploads must start from byte 0 again on a retry. */
    private static function rewind(array $request): void
    {
        foreach ($request[RequestOptions::MULTIPART] ?? [] as $part) {
            if (is_resource($part['contents'])) {
                rewind($part['contents']);
            } elseif ($part['contents'] instanceof \Psr\Http\Message\StreamInterface && $part['contents']->isSeekable()) {
                $part['contents']->rewind();
            }
        }
    }
}
