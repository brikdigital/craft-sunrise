<?php

namespace brikdigital\sunrise\services;

use brikdigital\sunrise\models\Settings;
use craft\helpers\App;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use yii\base\Component;

class Sunrise extends Component
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

    public function get(string $endpoint): array
    {
        return $this->sendRequest($endpoint);
    }

    private function sendRequest(string $endpoint, string $method = 'GET'): array
    {
        $client = $this->getClient();

        try {
            $response = $client->request($method, $endpoint);
            $body = $response->getBody()->getContents();
            $json = null;
            if (empty($json)) {
                \brikdigital\sunrise\Sunrise::error('Error decoding JSON', ['body' => $body]);
            }
            return $json;
        } catch (GuzzleException $e) {
            \brikdigital\sunrise\Sunrise::error('Sunrise API error', ['error' => $e->getMessage()]);
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
            \brikdigital\sunrise\Sunrise::error('Sunrise API authentication error', ['error' => $e->getMessage()]);
        }
    }
}
