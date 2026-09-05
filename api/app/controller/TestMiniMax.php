<?php
declare(strict_types=1);

namespace app\controller;

use think\Response;

class TestMiniMax extends BaseController
{
    public function test(): Response
    {
        $start = microtime(true);

        try {
            $baseUrl = rtrim(config('ai.minimax.base_url', 'https://api.minimaxi.com/anthropic'), '/');
            $url = $baseUrl . '/v1/messages';
            $authToken = config('ai.minimax.auth_token', '');

            $client = new \GuzzleHttp\Client([
                'timeout' => 10,
                'connect_timeout' => 5,
                'verify' => false,
                'http_errors' => false,
                'curl' => [
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_SSL_VERIFYHOST => false,
                ],
            ]);

            $response = $client->post($url, [
                'json' => [
                    'model' => 'MiniMax-M2.7',
                    'messages' => [['role' => 'user', 'content' => 'Hello, write a short marketing text for a hotpot restaurant']],
                    'max_tokens' => 100,
                ],
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $authToken,
                    'anthropic-version' => '2023-06-01',
                ],
            ]);

            $elapsed = round(microtime(true) - $start, 2);
            $body = json_decode($response->getBody()->getContents(), true);

            return $this->success([
                'elapsed' => $elapsed,
                'status' => $response->getStatusCode(),
                'response' => $body,
            ], 'Test successful');

        } catch (\Exception $e) {
            $elapsed = round(microtime(true) - $start, 2);
            return $this->error('Test failed after ' . $elapsed . 's: ' . $e->getMessage());
        }
    }
}