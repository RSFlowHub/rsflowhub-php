<?php

declare(strict_types=1);

namespace RSFlowHub;

/**
 * Official PHP client for the RSFlowHub AI API.
 *
 *   $client = new \RSFlowHub\RSFlowHub('rfh_...');   // or set RSFLOWHUB_API_KEY
 *   $result = $client->ai->sentimentAnalysis(['text' => 'Loved it!']);
 *   echo $result['sentiment'];
 *
 * Methods are not hard-coded: any AI endpoint, including ones added after this
 * release, is $client->ai->{camelCaseSlug}([...]). See README.md.
 *
 * <generated-resources>
 * @property-read Resources\Account $account Account
 * @property-read Resources\Ai $ai AI APIs
 * @property-read Resources\Chatbots $chatbots Chatbots
 * @property-read Resources\FaqCollections $faqCollections FAQ Collections
 * @property-read Resources\KnowledgeBases $knowledgeBases Knowledge Bases
 * @property-read Resources\System $system System
 * </generated-resources>
 */
final class RSFlowHub
{
    public const VERSION = '1.0.0';

    private readonly Options $options;

    private readonly Transport $transport;

    private readonly Operations $operations;

    /** @var array<string, Resource> */
    private array $resources = [];

    /**
     * @param string|null $apiKey  your key (rfh_...); falls back to RSFLOWHUB_API_KEY
     * @param array{
     *     base_url?: string, timeout?: float|int, max_retries?: int, headers?: array<string, string>,
     *     http_client?: \GuzzleHttp\ClientInterface, live_spec?: bool, sleep?: callable(float): void
     * } $options
     */
    public function __construct(?string $apiKey = null, array $options = [])
    {
        $this->options = Options::from($apiKey, $options);
        $this->transport = new Transport($this->options);
        $this->operations = new Operations($this->transport, $this->options->liveSpec);
    }

    /**
     * Call any route directly, e.g. request('POST', '/ai/translation', ['text' => ..., 'target_language' => 'French']).
     *
     * @param array<string, mixed> $body  JSON body; a value that is a stream/SplFileInfo makes it multipart
     * @param array<string, mixed> $query query string
     */
    public function request(string $method, string $path, array $body = [], array $query = []): Response
    {
        return $this->transport->send($method, $path, $body, $query);
    }

    /** Resource group by name: resource('knowledgeBases'). */
    public function resource(string $name): Resource
    {
        if (! isset($this->resources[$name])) {
            $class = __NAMESPACE__ . '\\Resources\\' . ucfirst($name);
            $this->resources[$name] = class_exists($class) && is_subclass_of($class, Resource::class)
                ? new $class($this, $name)
                : new Resource($this, $name);
        }

        return $this->resources[$name];
    }

    public function __get(string $name): Resource
    {
        return $this->resource($name);
    }

    /** $client->ai() works like $client->ai (needed by the Laravel facade). */
    public function __call(string $name, array $arguments): Resource
    {
        if ($arguments) {
            throw new \BadMethodCallException("Call {$name} as a resource: \$client->{$name}->method([...]).");
        }

        return $this->resource($name);
    }

    public function getOptions(): Options
    {
        return $this->options;
    }

    /** @internal */
    public function operations(): Operations
    {
        return $this->operations;
    }
}
