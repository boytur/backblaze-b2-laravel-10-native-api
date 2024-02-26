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

        // Get authorization token and apiUrl using B2_ACCOUNT_ID and B2_APPLICATION_KEY
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
                $apiUrl = $responseData['apiUrl'];

                // Put it to cache
                Cache::put('authorizationToken', $authorizationToken);
                Cache::put('apiUrl', $apiUrl);

                $this->uploadUrlAndAuthorization();
            }

        } catch (\Exception $e) {
            return 'Failed to connect to Backblaze B2 API: ' . $e->getMessage();
        }

        $this->info('B2 Authorization token and Upload URL have been refreshed successfully.');
    }

    // Get uploadUrl and uploadUrl authorization token using authorizationToken and apiUrl
    public function uploadUrlAndAuthorization()
    {
        try {

            $apiUrl = env('apiUrl');
            $b2 = new Client([
                'base_uri' => $apiUrl,
                'headers' => [
                    'Authorization' => Cache::get('authorizationToken')
                ]
            ]);

            $apiUrl = Cache::get('apiUrl');
            $response = $b2->get($apiUrl .'/b2api/v3/b2_get_upload_url?bucketId='.env('B2_BUCKET_ID '));

            if ($response->getStatusCode() === 200) {
                $res = $response->getBody()->getContents();
                $responseData = json_decode($res, true);
                $uploadUrl = $responseData['uploadUrl'];
                $authorizationTokenUrl = $responseData['authorizationToken'];

                Cache::put('uploadUrl', $uploadUrl);
                Cache::put('authorizationTokenUrl', $authorizationTokenUrl);
            }

        } catch (\Exception $e) {
            return 'Fail to get authorization token and apiUrl' . $e->getMessage();
        }
    }
}
