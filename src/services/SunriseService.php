<?php

namespace brikdigital\sunrise\services;

use brikdigital\sunrise\models\Settings;
use craft\helpers\App;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use yii\base\Component;

class SunriseService extends Component
{
    private Settings $settings;
    private string $baseUri;
    private Client $client;

    public function __construct($config = [])
    {
        $this->settings = \brikdigital\sunrise\Sunrise::getInstance()->getSettings();
        $this->baseUri = rtrim(App::parseEnv($this->settings->apiUrl), '/') . '/api/v2/';

        parent::__construct();
    }

    public function get(string $endpoint, array $query = []): array
    {
        return $this->sendRequest($endpoint, $query);
    }

    public function getAll(string $endpoint, array $query = [], int $offset = 0, int $pageSize = 1000): array
    {
        $query = array_merge($query, [
            'limit' => $pageSize,
            'offset' => $offset
        ]);
        $response = $this->get($endpoint, $query);

        if (count($response) == $pageSize) {
            $nextPage = $this->getAll($endpoint, $query, $offset + $pageSize, $pageSize);
            $response = array_merge($response, $nextPage);
        }

        return $response;
    }

    public function post(string $endpoint, array $body, array $query = []): array
    {
        return $this->sendRequest($endpoint, $query, $body, 'POST');
    }

    private function sendRequest(string $endpoint, array $query = [], array $body = [], string $method = 'GET'): array
    {
        $client = $this->getClient();

        try {
            $response = $client->request($method, $endpoint, [
                'query' => $query,
                'json' => $body
            ]);
            $contents = $response->getBody()->getContents();
            $json = json_decode($contents, true);
            if ($json === null) {
                \brikdigital\sunrise\Sunrise::error('Error decoding JSON', ['contents' => $contents]);
            }
            return $json;
        } catch (GuzzleException $e) {
            \brikdigital\sunrise\Sunrise::error('Sunrise API error', ['error' => $e->getResponse()->getBody()->getContents()], $e->getCode());
        }
    }

    private function getClient(): Client
    {
        if (!empty($this->client)) {
            return $this->client;
        }

        $authentication = $this->authenticate();
        if (empty($authentication->token)) {
            \brikdigital\sunrise\Sunrise::error('Unable to authenticate with Sunrise API', ['response' => $authentication]);
        }

        return $this->client = new Client([
            'base_uri' => $this->baseUri,
            'headers' => [
                'Authorization' => "Bearer $authentication->token",
            ]
        ]);
    }

    private function authenticate(): ?object
    {
        $authenticationClient = new Client([
            'base_uri' => $this->baseUri,
            'headers' => [
                'X-Apikey' => App::parseEnv($this->settings->apiKey),
            ]
        ]);

        try {
            $response = $authenticationClient->get('authenticate', [
                'query' => [
                    'merchantId' => $this->settings->merchantId,
                    'channelId' => $this->settings->channelId,
                ]
            ]);
            return json_decode($response->getBody()->getContents());
        } catch (GuzzleException $e) {
            \brikdigital\sunrise\Sunrise::error('Sunrise API authentication error', ['error' => $e->getResponse()->getBody()->getContents()]);
        }
    }
}
