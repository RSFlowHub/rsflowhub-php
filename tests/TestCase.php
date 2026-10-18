<?php

declare(strict_types=1);

namespace RSFlowHub\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response as HttpResponse;
use PHPUnit\Framework\TestCase as BaseTestCase;
use Psr\Http\Message\RequestInterface;
use RSFlowHub\RSFlowHub;

abstract class TestCase extends BaseTestCase
{
    protected MockHandler $mock;

    /** @var list<array{request: RequestInterface}> */
    protected array $history = [];

    /** @var list<float> */
    protected array $sleeps = [];

    protected function client(array $options = []): RSFlowHub
    {
        $this->mock = new MockHandler();
        $this->history = [];
        $stack = HandlerStack::create($this->mock);
        $stack->push(Middleware::history($this->history));

        return new RSFlowHub('rfh_test1234_secret', $options + [
            'base_url' => 'https://api.test/api/v1',
            'http_client' => new Client(['handler' => $stack]),
            'sleep' => function (float $seconds): void {
                $this->sleeps[] = $seconds;
            },
        ]);
    }

    protected function queue(int $status, array $body, array $headers = []): void
    {
        $this->mock->append(new HttpResponse($status, ['Content-Type' => 'application/json'] + $headers, json_encode($body)));
    }

    protected function ok(array $data = [], array $headers = []): void
    {
        $this->queue(200, [
            'success' => true,
            'request_id' => 'req_123',
            'data' => $data,
            'meta' => ['version' => 'v1', 'timestamp' => '2026-10-18T10:00:00Z'],
        ], $headers);
    }

    protected function error(int $status, string $code, string $message, array $details = [], array $headers = []): void
    {
        $this->queue($status, [
            'success' => false,
            'request_id' => 'req_err',
            'error' => ['code' => $code, 'message' => $message, 'details' => $details],
            'meta' => ['version' => 'v1'],
        ], $headers);
    }

    protected function lastRequest(): RequestInterface
    {
        return end($this->history)['request'];
    }

    protected function jsonBody(RequestInterface $request): array
    {
        return json_decode((string) $request->getBody(), true) ?? [];
    }
}
