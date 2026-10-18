<?php

declare(strict_types=1);

namespace RSFlowHub\Tests;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response as HttpResponse;
use RSFlowHub\Exceptions\ApiException;
use RSFlowHub\Exceptions\AuthenticationException;
use RSFlowHub\Exceptions\ConnectionException;
use RSFlowHub\Exceptions\InsufficientCreditsException;
use RSFlowHub\Exceptions\NotFoundException;
use RSFlowHub\Exceptions\PermissionDeniedException;
use RSFlowHub\Exceptions\RateLimitException;
use RSFlowHub\Exceptions\ServerException;
use RSFlowHub\Exceptions\ServiceUnavailableException;
use RSFlowHub\Exceptions\ValidationException;

final class ErrorsAndRetriesTest extends TestCase
{
    private function catch(callable $call): ApiException
    {
        try {
            $call();
        } catch (ApiException $e) {
            return $e;
        }
        $this->fail('No ApiException thrown');
    }

    public function test_validation_errors_expose_code_message_and_fields(): void
    {
        $client = $this->client();
        $this->error(422, 'VALIDATION_ERROR', 'The given data was invalid.', ['text' => ['The text field is required.']]);

        $e = $this->catch(fn () => $client->ai->sentimentAnalysis([]));

        $this->assertInstanceOf(ValidationException::class, $e);
        $this->assertSame('VALIDATION_ERROR', $e->getErrorCode());
        $this->assertSame('The given data was invalid.', $e->getMessage());
        $this->assertSame(422, $e->getStatus());
        $this->assertSame('req_err', $e->getRequestId());
        $this->assertSame(['text' => ['The text field is required.']], $e->getErrors());
    }

    public function test_insufficient_credits_exposes_required_and_available(): void
    {
        $client = $this->client();
        $this->error(402, 'INSUFFICIENT_CREDITS', 'Not enough credits.', ['required_credits' => 3, 'available_credits' => 1]);

        $e = $this->catch(fn () => $client->ai->reviewSummary(['reviews' => ['ok']]));

        $this->assertInstanceOf(InsufficientCreditsException::class, $e);
        $this->assertSame(3, $e->getRequiredCredits());
        $this->assertSame(1, $e->getAvailableCredits());
    }

    /** @return iterable<array{int, string, class-string}> */
    public static function errorCodes(): iterable
    {
        yield [401, 'INVALID_API_KEY', AuthenticationException::class];
        yield [401, 'API_KEY_MISSING', AuthenticationException::class];
        yield [403, 'SPENDING_LIMIT_REACHED', PermissionDeniedException::class];
        yield [403, 'USER_INACTIVE', PermissionDeniedException::class];
        yield [404, 'ENDPOINT_NOT_FOUND', NotFoundException::class];
        yield [500, 'INTERNAL_SERVER_ERROR', ServerException::class];
        yield [502, 'AI_PROVIDER_ERROR', ServiceUnavailableException::class];
        yield [405, 'METHOD_NOT_ALLOWED', ApiException::class];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('errorCodes')]
    public function test_error_codes_map_to_typed_exceptions(int $status, string $code, string $class): void
    {
        $client = $this->client(['max_retries' => 0]);
        $this->error($status, $code, 'Nope');

        $e = $this->catch(fn () => $client->ai->chat(['message' => 'hi']));

        $this->assertSame($class, $e::class);
        $this->assertSame($code, $e->getErrorCode());
    }

    public function test_429_and_503_are_retried_honouring_retry_after(): void
    {
        $client = $this->client();
        $this->error(429, 'RATE_LIMIT_EXCEEDED', 'Slow down', [], ['Retry-After' => '2']);
        $this->error(503, 'PROVIDER_UNAVAILABLE', 'Busy');
        $this->ok(['reply' => 'Hello']);

        $res = $client->ai->chat(['message' => 'hi']);

        $this->assertSame('Hello', $res['reply']);
        $this->assertCount(3, $this->history);
        $this->assertSame(2.0, $this->sleeps[0]);
        $this->assertGreaterThanOrEqual(1.0, $this->sleeps[1]); // 2nd backoff: 1s + jitter
    }

    public function test_retries_give_up_after_max_retries(): void
    {
        $client = $this->client(['max_retries' => 1]);
        $this->error(429, 'RATE_LIMIT_EXCEEDED', 'Slow down', [], ['Retry-After' => '7']);
        $this->error(429, 'RATE_LIMIT_EXCEEDED', 'Slow down', [], ['Retry-After' => '7']);

        $e = $this->catch(fn () => $client->ai->chat(['message' => 'hi']));

        $this->assertInstanceOf(RateLimitException::class, $e);
        $this->assertSame(7, $e->getRetryAfter());
        $this->assertCount(2, $this->history);
    }

    public function test_validation_and_server_errors_are_not_retried(): void
    {
        $client = $this->client();
        $this->error(500, 'INTERNAL_SERVER_ERROR', 'Oops');

        $this->catch(fn () => $client->ai->chat(['message' => 'hi']));

        $this->assertCount(1, $this->history);
        $this->assertSame([], $this->sleeps);
    }

    public function test_connection_failures_are_retried_then_raised(): void
    {
        $client = $this->client(['max_retries' => 1]);
        $request = new Request('POST', 'https://api.test/api/v1/ai/chat');
        $this->mock->append(new ConnectException('timeout', $request), new ConnectException('timeout', $request));

        $this->expectException(ConnectionException::class);
        try {
            $client->ai->chat(['message' => 'hi']);
        } finally {
            $this->assertCount(1, $this->sleeps);
        }
    }

    public function test_non_json_error_pages_still_map_by_status(): void
    {
        $client = $this->client(['max_retries' => 0]);
        $this->mock->append(new HttpResponse(503, [], '<html>Bad gateway</html>'));

        $e = $this->catch(fn () => $client->ai->chat(['message' => 'hi']));

        $this->assertInstanceOf(ServiceUnavailableException::class, $e);
        $this->assertSame('PROVIDER_UNAVAILABLE', $e->getErrorCode());
    }
}
