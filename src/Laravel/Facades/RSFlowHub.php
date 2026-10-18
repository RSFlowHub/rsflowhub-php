<?php

declare(strict_types=1);

namespace RSFlowHub\Laravel\Facades;

use Illuminate\Support\Facades\Facade;

/**
 *   RSFlowHub::ai()->sentimentAnalysis(['text' => 'Great service']);
 *   RSFlowHub::knowledgeBases()->ask(12, ['question' => 'Refund policy?']);
 *
 * @method static \RSFlowHub\Resources\Ai ai()
 * @method static \RSFlowHub\Resources\KnowledgeBases knowledgeBases()
 * @method static \RSFlowHub\Resources\Chatbots chatbots()
 * @method static \RSFlowHub\Resources\FaqCollections faqCollections()
 * @method static \RSFlowHub\Resources\Account account()
 * @method static \RSFlowHub\Response request(string $method, string $path, array $body = [], array $query = [])
 * @method static \RSFlowHub\Resource resource(string $name)
 *
 * @see \RSFlowHub\RSFlowHub
 */
final class RSFlowHub extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'rsflowhub';
    }
}
