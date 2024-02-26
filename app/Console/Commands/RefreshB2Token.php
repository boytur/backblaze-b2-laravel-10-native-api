<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use GuzzleHttp\Client;
use Cache;

class RefreshB2Token extends Command
{
    protected $signature = 'b2:refresh-token';
    protected $description = 'Refreshes the authorization token and upload URL from Backblaze B2';

    public function handle()
    {
        $authorizationToken = "";

        $b2 = new Client([
            'base_uri' => env('B2_API'),
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode(env('B2_ACCOUNT_ID') . ':' . env('B2_APPLICATION_KEY'))
            ]
        ]);

        try {
            $response = $b2->get('/b2api/v1/b2_authorize_account');
            if ($response->getStatusCode() === 200) {
                $res = $response->getBody()->getContents();
                $responseData = json_decode($res, true);
                $authorizationToken = $responseData['authorizationToken'];
                $b2AuhtApi = $responseData['apiUrl'];
                Cache::put('authorizationToken', $authorizationToken);
                Cache::put('b2AuhtApi', $b2AuhtApi);
                $this->getUrl();
            }

        } catch (\Exception $e) {
            return 'Failed to connect to Backblaze B2 API: ' . $e->getMessage();
        }

        $this->info('B2 Authorization token and Upload URL have been refreshed successfully.');
    }

    public function getUrl()
    {
        $apiUrl = env('B2_AUTH_API');
        $b2 = new Client([
            'base_uri' => $apiUrl,
            'headers' => [
                'Authorization' => Cache::get('authorizationToken')
            ]
        ]);

        $b2AuthApi = Cache::get('b2AuhtApi');
        $response = $b2->get('/b2api/v3/b2_get_upload_url?bucketId=' . $b2AuthApi);
        if ($response->getStatusCode() === 200) {
            // การเชื่อมต่อสำเร็จ
            $res = $response->getBody()->getContents();
            $responseData = json_decode($res, true);
            $uploadUrl = $responseData['uploadUrl'];
            $authorizationTokenUrl = $responseData['authorizationToken'];

            Cache::put('uploadUrl', $uploadUrl);
            Cache::put('authorizationTokenUrl', $authorizationTokenUrl);
        }
    }
}
