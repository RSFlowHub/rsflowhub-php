<?php

declare(strict_types=1);

namespace RSFlowHub;

use RSFlowHub\Exceptions\RSFlowHubException;

/**
 * Knows which HTTP call each SDK method makes.
 *
 * 1. The bundled map (src/Generated/Operations.php), generated from the live
 *    OpenAPI document by bin/generate.php.
 * 2. Not there, AI resource: POST /ai/{method-in-kebab-case}. Every flat AI
 *    endpoint works this way, including ones released after this SDK version.
 * 3. Not there, other resource: read GET /openapi.json once (x-rsflowhub-sdk)
 *    and look again, so new knowledge-base/chatbot routes work too.
 *
 * @internal
 */
final class Operations
{
    /** @var array<string, array<string, array{0: string, 1: string}>> resource => method => [http method, path] */
    private array $map;

    private bool $liveLoaded = false;

    public function __construct(private readonly Transport $transport, private readonly bool $liveSpec)
    {
        $this->map = require __DIR__ . '/Generated/Operations.php';
    }

    /**
     * @return array{0: string, 1: string} [http method, path template]
     */
    public function find(string $resource, string $method): array
    {
        if (isset($this->map[$resource][$method])) {
            return $this->map[$resource][$method];
        }

        if ($resource === 'ai') {
            return ['POST', '/ai/' . self::kebab($method)];
        }

        if ($this->liveSpec && ! $this->liveLoaded) {
            $this->loadLiveSpec();
            if (isset($this->map[$resource][$method])) {
                return $this->map[$resource][$method];
            }
        }

        $known = array_keys($this->map[$resource] ?? []);
        throw new \BadMethodCallException(sprintf(
            'Unknown RSFlowHub method %s->%s(). %s Use $client->request() for any other route.',
            $resource,
            $method,
            $known ? 'Known methods: ' . implode(', ', $known) . '.' : 'Unknown resource.'
        ));
    }

    /** @return list<string> */
    public function resources(): array
    {
        return array_keys($this->map);
    }

    /** @return array<string, array<string, array{0: string, 1: string}>> */
    public function all(): array
    {
        return $this->map;
    }

    private function loadLiveSpec(): void
    {
        $this->liveLoaded = true;

        try {
            $spec = $this->transport->send('GET', '/openapi.json')->body;
        } catch (RSFlowHubException) {
            return; // offline or older server: keep the bundled map
        }

        foreach (self::fromSpec($spec) as $resource => $methods) {
            $this->map[$resource] = ($this->map[$resource] ?? []) + $methods;
        }
    }

    /**
     * Read x-rsflowhub-sdk names out of an OpenAPI document.
     *
     * @param array<string, mixed> $spec
     * @return array<string, array<string, array{0: string, 1: string}>>
     */
    public static function fromSpec(array $spec): array
    {
        $map = [];
        foreach ((array) ($spec['paths'] ?? []) as $path => $operations) {
            foreach ((array) $operations as $httpMethod => $operation) {
                $sdk = $operation['x-rsflowhub-sdk'] ?? null;
                if (! is_array($sdk) || empty($sdk['resource']) || empty($sdk['method'])) {
                    continue;
                }
                $map[(string) $sdk['resource']][(string) $sdk['method']] = [strtoupper((string) $httpMethod), (string) $path];
            }
        }
        ksort($map);

        return $map;
    }

    public static function kebab(string $name): string
    {
        return strtolower((string) preg_replace('/(?<=[a-z0-9])([A-Z])/', '-$1', $name));
    }
}
