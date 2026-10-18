<?php

declare(strict_types=1);

namespace RSFlowHub;

use InvalidArgumentException;

/**
 * A group of API methods, e.g. $client->ai or $client->knowledgeBases.
 *
 * Call any method by its SDK name. Path ids come first, then the body
 * (query string for GET calls):
 *
 *   $client->ai->sentimentAnalysis(['text' => 'Great product!']);
 *   $client->knowledgeBases->ask(12, ['question' => 'How do refunds work?']);
 *   $client->chatbots->getConversation(3, 'conv_abc');
 *
 * Path ids can also be passed by name inside the array: ask(['id' => 12, ...]).
 */
class Resource
{
    public function __construct(
        protected readonly RSFlowHub $client,
        protected readonly string $name,
    ) {
    }

    /**
     * @param array<int, mixed> $arguments
     */
    public function __call(string $method, array $arguments): Response
    {
        [$httpMethod, $template] = $this->client->operations()->find($this->name, $method);

        $ids = [];
        $params = [];
        foreach ($arguments as $argument) {
            if (is_array($argument)) {
                $params = $argument;
                break;
            }
            $ids[] = $argument;
        }

        $path = preg_replace_callback('/\{([^}]+)\}/', function (array $m) use (&$ids, &$params, $method): string {
            if ($ids) {
                $value = array_shift($ids);
            } elseif (array_key_exists($m[1], $params)) {
                $value = $params[$m[1]];
                unset($params[$m[1]]);
            } else {
                throw new InvalidArgumentException("{$this->name}->{$method}() needs the \"{$m[1]}\" path parameter.");
            }
            if (! is_scalar($value) || (string) $value === '') {
                throw new InvalidArgumentException("{$this->name}->{$method}(): \"{$m[1]}\" must be a non-empty id.");
            }

            return rawurlencode((string) $value);
        }, $template);

        if ($ids) {
            throw new InvalidArgumentException("{$this->name}->{$method}() got more ids than its path {$template} takes.");
        }

        return $httpMethod === 'GET'
            ? $this->client->request($httpMethod, (string) $path, [], $params)
            : $this->client->request($httpMethod, (string) $path, $params);
    }
}
