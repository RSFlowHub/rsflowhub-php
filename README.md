# RSFlowHub PHP SDK

[![Latest Version](https://img.shields.io/packagist/v/rsflowhub/rsflowhub-php.svg)](https://packagist.org/packages/rsflowhub/rsflowhub-php)
[![Tests](https://github.com/RSFlowHub/rsflowhub-php/actions/workflows/tests.yml/badge.svg)](https://github.com/RSFlowHub/rsflowhub-php/actions/workflows/tests.yml)
[![PHP](https://img.shields.io/packagist/php-v/rsflowhub/rsflowhub-php.svg)](https://packagist.org/packages/rsflowhub/rsflowhub-php)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)

The official PHP library for the [RSFlowHub AI API](https://rsflowhub.com). Add sentiment analysis, translation, AI chat, PII redaction, spam detection, review and support-ticket AI, knowledge-base search (RAG) and chatbots to any PHP or Laravel app with one API key.

- **Never out of date.** Methods are not hard-coded. A new RSFlowHub API works the day it ships: `$client->ai->newEndpointName([...])`. No SDK upgrade needed.
- **Typed errors.** `ValidationException`, `InsufficientCreditsException`, `RateLimitException` and more, each with the API's error code, message and request id.
- **Retries built in.** 429 and temporary 5xx answers are retried with backoff, respecting `Retry-After`. Those answers never charge credits.
- **IDE autocomplete.** Every method has typed parameters, generated from the [RSFlowHub OpenAPI spec](https://rsflowhub.com/api/v1/openapi.json).
- **Laravel ready.** Auto-discovered service provider and `RSFlowHub` facade.

PHP 8.1+. Other languages: [Node.js / TypeScript](https://github.com/RSFlowHub/rsflowhub-node) · [Python](https://github.com/RSFlowHub/rsflowhub-python) · [Examples](https://github.com/RSFlowHub/rsflowhub-examples)

## Install

```bash
composer require rsflowhub/rsflowhub-php
```

Get an API key from the [RSFlowHub dashboard](https://rsflowhub.com/dashboard) (free credits included).

## Quick start

```php
use RSFlowHub\RSFlowHub;

$client = new RSFlowHub('rfh_your_key');   // or set RSFLOWHUB_API_KEY and call new RSFlowHub()

$result = $client->ai->sentimentAnalysis([
    'text' => 'Setup took two minutes and support answered right away.',
]);

echo $result->result('sentiment');    // "positive"
echo $result->result('confidence');   // 0.98
echo $result->requestId;              // "req_..." quote it when you contact support
```

Every API answer is the same envelope `{success, request_id, data, meta}`; AI endpoints put their output in `data.result`. The SDK returns a read-only `RSFlowHub\Response`:

- `$res->result()` / `$res->result('key')`: the AI output (`data.result`), or `data` when there is no `result`
- `$res['key']`, `$res->key`, `$res->get('a.b.c')`, `$res->toArray()`: anything in `data`
- `$res->requestId`, `$res->meta`, `$res->rateLimit()`, `$res->header('X-Request-Id')`

## Calling any API

The method name is the endpoint slug in camelCase:

| API | Call |
| --- | --- |
| `POST /ai/sentiment-analysis` | `$client->ai->sentimentAnalysis(['text' => '...'])` |
| `POST /ai/translation` | `$client->ai->translation(['text' => 'Hello', 'target_language' => 'French'])` |
| `POST /ai/chat` | `$client->ai->chat(['message' => 'Hi!'])` |
| `POST /ai/pii-redaction` | `$client->ai->piiRedaction(['text' => 'Call me on 555-0100'])` |
| `POST /ai/ticket-analyze` | `$client->ai->ticketAnalyze(['ticket' => 'My order never arrived'])` |
| `POST /ai/any-new-api` | `$client->ai->anyNewApi([...])` (works without an SDK update) |

All methods with their parameters: [docs/ENDPOINTS.md](docs/ENDPOINTS.md). API docs: [rsflowhub.com/docs](https://rsflowhub.com/docs).

### Knowledge bases (RAG) and chatbots

Ids in the URL come first, then the body:

```php
$kb = $client->knowledgeBases->create(['name' => 'Help center']);
$kbId = $kb->get('knowledge_base.id');

$client->knowledgeBases->createDocument($kbId, ['file' => new SplFileInfo('refund-policy.pdf')]);

$answer = $client->knowledgeBases->ask($kbId, ['question' => 'Can I get a refund after 30 days?']);

$reply = $client->chatbots->sendMessage($chatbotId, ['message' => 'Where is my order?']);
```

Ids can also go in the array by name: `ask(['id' => $kbId, 'question' => '...'])`. A value that is an `SplFileInfo`, a stream resource or a PSR-7 stream turns the request into a file upload.

### Any route

```php
$res = $client->request('GET', '/account/usage', query: ['page' => 2]);
```

## Errors

```php
use RSFlowHub\Exceptions\{ApiException, ConnectionException, InsufficientCreditsException, RateLimitException, ValidationException};

try {
    $res = $client->ai->textSummarize(['text' => $article]);
} catch (ValidationException $e) {
    print_r($e->getErrors());                       // ['text' => ['The text field is required.']]
} catch (InsufficientCreditsException $e) {
    echo "Need {$e->getRequiredCredits()} credits, have {$e->getAvailableCredits()}.";
} catch (RateLimitException $e) {
    sleep($e->getRetryAfter() ?? 1);                // already retried max_retries times
} catch (ApiException $e) {
    echo $e->getErrorCode(), ': ', $e->getMessage(), ' (', $e->getRequestId(), ')';
} catch (ConnectionException $e) {
    // network problem, timeout or DNS
}
```

| Exception | When | Error codes |
| --- | --- | --- |
| `AuthenticationException` | 401 | `API_KEY_MISSING`, `INVALID_API_KEY` |
| `InsufficientCreditsException` | 402 | `INSUFFICIENT_CREDITS` |
| `PermissionDeniedException` | 403 | `SPENDING_LIMIT_REACHED`, `USER_INACTIVE` |
| `NotFoundException` | 404 | `ENDPOINT_NOT_FOUND` |
| `ValidationException` | 422 | `VALIDATION_ERROR` |
| `RateLimitException` | 429 | `RATE_LIMIT_EXCEEDED` |
| `ServerException` | 500 | `INTERNAL_SERVER_ERROR` |
| `ServiceUnavailableException` | 502 / 503 | `PROVIDER_UNAVAILABLE`, `AI_PROVIDER_ERROR` |
| `ConnectionException` | no answer | |

All of them extend `RSFlowHub\Exceptions\RSFlowHubException`.

## Options

```php
$client = new RSFlowHub('rfh_your_key', [
    'base_url'    => 'https://rsflowhub.com/api/v1', // or RSFLOWHUB_BASE_URL
    'timeout'     => 60,      // seconds
    'max_retries' => 2,       // for 429, 502, 503, 504 and connection errors
    'headers'     => [],      // extra headers on every request
    'http_client' => null,    // your own GuzzleHttp\ClientInterface (proxies, logging, tests)
    'live_spec'   => true,    // look up unknown non-AI methods in the live OpenAPI spec
]);
```

Rate-limit state after a call: `$res->rateLimit()` returns `['limit' => 60, 'remaining' => 59, 'reset' => null]`.

## Laravel

The service provider and facade are auto-discovered. Add to `config/services.php`:

```php
'rsflowhub' => [
    'key' => env('RSFLOWHUB_API_KEY'),
],
```

```php
use RSFlowHub\Laravel\Facades\RSFlowHub;

$result = RSFlowHub::ai()->spamDetection(['text' => $request->input('comment')]);

// or inject it
public function __invoke(\RSFlowHub\RSFlowHub $client) { /* $client->ai->... */ }
```

## How it stays up to date

RSFlowHub publishes a machine-readable [OpenAPI document](https://rsflowhub.com/api/v1/openapi.json) generated from the API's own routes and validation rules. This SDK uses it in three ways:

1. **AI endpoints** are called by name, so new ones work at once.
2. **Other new routes** (for example a new knowledge-base action) are looked up in the live document the first time you call them.
3. **Autocomplete and docs** (`src/Resources`, `docs/ENDPOINTS.md`) are regenerated every week by a GitHub workflow that opens a pull request when the API changed.

To regenerate by hand: `composer generate` (or `php bin/generate.php <url-or-file>`).

## Contributing

Issues and pull requests are welcome. Run `composer install && composer test`. Do not edit generated files (`src/Generated`, `src/Resources`, `docs/ENDPOINTS.md`); change `bin/generate.php` instead.

## License

MIT. See [LICENSE](LICENSE).
