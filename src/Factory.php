<?php

declare(strict_types=1);

namespace Nomba\Sdk;

use GuzzleHttp\Client;
use Nomba\Sdk\Contracts\HttpClientInterface;
use Nomba\Sdk\Enums\Environment;
use Nomba\Sdk\Http\Authenticator;
use Nomba\Sdk\Http\GuzzleHttpClient;
use Nomba\Sdk\Resources\WebhookResource;
use Nomba\Sdk\Support\NombaConfig;
use Nomba\Sdk\Webhooks\EventParser;
use Nomba\Sdk\Webhooks\PayloadNormalizer;
use Nomba\Sdk\Webhooks\SignatureVerifier;
use Psr\SimpleCache\CacheInterface;

final class Factory
{
    public static function make(
        string           $clientId,
        string           $clientSecret,
        string           $accountId,
        Environment      $environment   = Environment::Sandbox,
        float            $timeout       = 30.0,
        int              $retryAttempts = 3,
        string           $webhookSecret = '',
        ?CacheInterface  $cache         = null,
    ): NombaClient {
        $config = new NombaConfig(
            clientId:      $clientId,
            clientSecret:  $clientSecret,
            accountId:     $accountId,
            environment:   $environment,
            timeout:       $timeout,
            retryAttempts: $retryAttempts,
            webhookSecret: $webhookSecret,
        );

        return self::fromConfig($config, $cache);
    }

    public static function fromConfig(NombaConfig $config, ?CacheInterface $cache = null): NombaClient
    {
        $guzzle        = new Client(['base_uri' => $config->baseUrl(), 'timeout' => $config->timeout]);
        $authenticator = new Authenticator($config, $guzzle, $cache);
        $httpClient    = new GuzzleHttpClient($config, $authenticator);

        return new NombaClient($httpClient, self::buildWebhookResource($config));
    }

    public static function withClient(HttpClientInterface $client, NombaConfig $config): NombaClient
    {
        return new NombaClient($client, self::buildWebhookResource($config));
    }

    private static function buildWebhookResource(NombaConfig $config): WebhookResource
    {
        return new WebhookResource(
            new SignatureVerifier($config->webhookSecret),
            new EventParser(),
            new PayloadNormalizer(),
        );
    }
}
