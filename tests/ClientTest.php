<?php

declare(strict_types=1);

namespace RSFlowHub\Tests;

use GuzzleHttp\Psr7\Response as HttpResponse;
use InvalidArgumentException;
use RSFlowHub\Operations;
use RSFlowHub\Resources\Ai;
use RSFlowHub\Resources\KnowledgeBases;
use RSFlowHub\RSFlowHub;

final class ClientTest extends TestCase
{
    public function test_ai_call_posts_json_with_the_api_key_and_returns_data(): void
    {
        $client = $this->client();
        $this->ok(['sentiment' => 'positive', 'score' => 0.98], ['X-RateLimit-Limit' => '60', 'X-RateLimit-Remaining' => '59']);

        $res = $client->ai->sentimentAnalysis(['text' => 'Loved it']);

        $request = $this->lastRequest();
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('https://api.test/api/v1/ai/sentiment-analysis', (string) $request->getUri());
        $this->assertSame('rfh_test1234_secret', $request->getHeaderLine('X-API-Key'));
        $this->assertStringStartsWith('rsflowhub-php/', $request->getHeaderLine('User-Agent'));
        $this->assertSame(['text' => 'Loved it'], $this->jsonBody($request));

        $this->assertSame('positive', $res['sentiment']);
        $this->assertSame('positive', $res->sentiment);
        $this->assertSame(0.98, $res->get('score'));
        $this->assertSame('req_123', $res->requestId);
        $this->assertSame('v1', $res->meta['version']);
        $this->assertSame(['limit' => 60, 'remaining' => 59, 'reset' => null], $res->rateLimit());
    }

    public function test_result_reads_the_ai_output_inside_data_result(): void
    {
        $client = $this->client();
        $this->ok(['result' => ['sentiment' => 'positive', 'aspects' => [['name' => 'billing']]]]);
        $this->ok(['knowledge_base' => ['id' => 4]]);

        $ai = $client->ai->sentimentAnalysis(['text' => 'Loved it']);
        $this->assertSame('positive', $ai->result('sentiment'));
        $this->assertSame('billing', $ai->result('aspects.0.name'));
        $this->assertSame(['sentiment' => 'positive', 'aspects' => [['name' => 'billing']]], $ai->result());
        $this->assertNull($ai->result('missing'));

        $kb = $client->knowledgeBases->create(['name' => 'Help']);
        $this->assertSame(4, $kb->result('knowledge_base.id'));
    }

    public function test_ai_endpoints_released_after_this_sdk_still_work(): void
    {
        $client = $this->client();
        $this->ok(['ok' => true]);

        $client->ai->speechToTextV2(['audio_url' => 'https://example.com/a.mp3']);

        $this->assertSame('/api/v1/ai/speech-to-text-v2', $this->lastRequest()->getUri()->getPath());
        $this->assertCount(1, $this->history, 'no spec lookup is needed for AI endpoints');
    }

    public function test_path_ids_are_bound_in_order_or_by_name(): void
    {
        $client = $this->client();
        $this->ok();
        $this->ok();
        $this->ok();

        $client->knowledgeBases->ask(12, ['question' => 'Refunds?']);
        $this->assertSame('/api/v1/knowledge-bases/12/ask', $this->lastRequest()->getUri()->getPath());
        $this->assertSame(['question' => 'Refunds?'], $this->jsonBody($this->lastRequest()));

        $client->knowledgeBases->ask(['id' => 7, 'question' => 'Shipping?']);
        $this->assertSame('/api/v1/knowledge-bases/7/ask', $this->lastRequest()->getUri()->getPath());
        $this->assertSame(['question' => 'Shipping?'], $this->jsonBody($this->lastRequest()));

        $client->chatbots->getConversation(3, 'conv/a b');
        $this->assertSame('GET', $this->lastRequest()->getMethod());
        $this->assertSame('/api/v1/chatbots/3/conversations/conv%2Fa%20b', $this->lastRequest()->getUri()->getPath());
    }

    public function test_get_parameters_go_to_the_query_string(): void
    {
        $client = $this->client();
        $this->ok(['items' => []]);

        $client->account->usage(['page' => 2, 'per_page' => 50]);

        $request = $this->lastRequest();
        $this->assertSame('GET', $request->getMethod());
        $this->assertSame('page=2&per_page=50', $request->getUri()->getQuery());
        $this->assertSame('', (string) $request->getBody());
    }

    public function test_missing_path_id_is_a_clear_error(): void
    {
        $client = $this->client();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('needs the "id" path parameter');

        $client->knowledgeBases->ask(['question' => 'x']);
    }

    public function test_unknown_non_ai_methods_are_looked_up_in_the_live_spec_once(): void
    {
        $client = $this->client();
        $this->queue(200, ['openapi' => '3.0.3', 'paths' => [
            '/knowledge-bases/{id}/reindex' => ['post' => ['x-rsflowhub-sdk' => ['resource' => 'knowledgeBases', 'method' => 'reindex']]],
            '/webhooks' => ['get' => ['x-rsflowhub-sdk' => ['resource' => 'webhooks', 'method' => 'list']]],
        ]]);
        $this->ok(['queued' => true]);
        $this->ok(['webhooks' => []]);

        $res = $client->knowledgeBases->reindex(5);
        $client->webhooks->list();

        $this->assertTrue($res['queued']);
        $this->assertSame('/api/v1/openapi.json', $this->history[0]['request']->getUri()->getPath());
        $this->assertSame('/api/v1/knowledge-bases/5/reindex', $this->history[1]['request']->getUri()->getPath());
        $this->assertSame('/api/v1/webhooks', $this->history[2]['request']->getUri()->getPath());
        $this->assertCount(3, $this->history);
    }

    public function test_unknown_method_without_live_spec_explains_the_alternatives(): void
    {
        $client = $this->client(['live_spec' => false]);

        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('$client->request()');

        $client->knowledgeBases->doesNotExist(1);
    }

    public function test_request_reaches_any_route(): void
    {
        $client = $this->client();
        $this->ok(['translated' => 'Bonjour']);

        $res = $client->request('POST', '/ai/translation', ['text' => 'Hello', 'target_language' => 'French']);

        $this->assertSame('Bonjour', $res['translated']);
        $this->assertSame('/api/v1/ai/translation', $this->lastRequest()->getUri()->getPath());
    }

    public function test_files_are_uploaded_as_multipart_with_bracketed_arrays(): void
    {
        $client = $this->client();
        $this->ok(['document' => ['id' => 9]]);

        $file = tempnam(sys_get_temp_dir(), 'rf');
        file_put_contents($file, 'Refunds within 30 days.');

        $client->knowledgeBases->createDocument(4, [
            'file' => new \SplFileInfo($file),
            'metadata' => ['source' => 'faq'],
        ]);
        unlink($file);

        $request = $this->lastRequest();
        $body = (string) $request->getBody();
        $this->assertStringStartsWith('multipart/form-data', $request->getHeaderLine('Content-Type'));
        $this->assertStringContainsString('name="file"; filename="', $body);
        $this->assertStringContainsString('Refunds within 30 days.', $body);
        $this->assertStringContainsString('name="metadata[source]"', $body);
    }

    public function test_resources_are_typed_and_cached(): void
    {
        $client = $this->client();

        $this->assertInstanceOf(Ai::class, $client->ai);
        $this->assertInstanceOf(KnowledgeBases::class, $client->knowledgeBases());
        $this->assertSame($client->ai, $client->ai);
    }

    public function test_api_key_and_base_url_can_come_from_the_environment(): void
    {
        putenv('RSFLOWHUB_API_KEY=rfh_fromenv_x');
        putenv('RSFLOWHUB_BASE_URL=https://staging.test/api/v1/');
        try {
            $client = new RSFlowHub();
            $this->assertSame('rfh_fromenv_x', $client->getOptions()->apiKey);
            $this->assertSame('https://staging.test/api/v1', $client->getOptions()->baseUrl);
        } finally {
            putenv('RSFLOWHUB_API_KEY');
            putenv('RSFLOWHUB_BASE_URL');
        }
    }

    public function test_a_missing_api_key_fails_fast(): void
    {
        putenv('RSFLOWHUB_API_KEY');
        $this->expectException(InvalidArgumentException::class);
        new RSFlowHub();
    }

    public function test_camel_case_to_kebab_case(): void
    {
        $this->assertSame('sentiment-analysis', Operations::kebab('sentimentAnalysis'));
        $this->assertSame('pii-redaction', Operations::kebab('piiRedaction'));
        $this->assertSame('chat', Operations::kebab('chat'));
        $this->assertSame('text-to-speech2', Operations::kebab('textToSpeech2'));
    }

    public function test_bundled_map_covers_the_generated_resources(): void
    {
        $map = require __DIR__ . '/../src/Generated/Operations.php';

        $this->assertSame(['POST', '/ai/sentiment-analysis'], $map['ai']['sentimentAnalysis']);
        $this->assertSame(['POST', '/knowledge-bases/{id}/ask'], $map['knowledgeBases']['ask']);
        foreach (array_keys($map) as $resource) {
            $this->assertTrue(class_exists('RSFlowHub\\Resources\\' . ucfirst($resource)), "missing Resources\\{$resource}");
        }
    }

    public function test_non_json_success_is_an_sdk_error(): void
    {
        $client = $this->client();
        $this->mock->append(new HttpResponse(200, [], '<html>maintenance</html>'));

        $this->expectException(\RSFlowHub\Exceptions\RSFlowHubException::class);
        $client->ai->chat(['message' => 'hi']);
    }
}
