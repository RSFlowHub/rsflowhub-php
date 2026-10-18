<?php

// php examples/quickstart.php   (needs RSFLOWHUB_API_KEY)

require __DIR__ . '/../vendor/autoload.php';

use RSFlowHub\Exceptions\ApiException;
use RSFlowHub\RSFlowHub;

$client = new RSFlowHub();

try {
    $sentiment = $client->ai->sentimentAnalysis(['text' => 'Setup took two minutes and support answered right away.']);
    print_r($sentiment->result());

    $translation = $client->ai->translation(['text' => 'Thanks for your order!', 'target_language' => 'Spanish']);
    print_r($translation->result());
} catch (ApiException $e) {
    echo "{$e->getErrorCode()}: {$e->getMessage()} (request {$e->getRequestId()})\n";
}
