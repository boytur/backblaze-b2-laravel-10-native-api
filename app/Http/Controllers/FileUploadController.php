<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use GuzzleHttp\Client;
use Cache;

class FileUploadController extends Controller
{
    public function uploadToB2(Request $request)
    {
        $uploadUrl = Cache::get('uploadUrl');
        $uploadUrlAuthorizationToken = Cache::get('authorizationTokenUrl');

        if ($request->hasFile('file')) {

            $file = $request->file('file');
            $fileContent = file_get_contents($file->path());

            $client = new Client([
                'base_uri' => $uploadUrl,
                'headers' => [
                    'Authorization' => $uploadUrlAuthorizationToken,
                    'Content-Type' => 'application/octet-stream',
                    'X-Bz-File-Name' => $file->getClientOriginalName(),
                    'X-Bz-Content-Sha1' => sha1_file($file->path()),
                ],
                'body' => $fileContent,
            ]);

            try {
                $response = $client->post($uploadUrl);
                if ($response->getStatusCode() === 200) {
                    return 'File ' . $file->getClientOriginalName() . ' uploaded successfully.';
                } else {
                    return 'Failed to upload file ' . $file->getClientOriginalName();
                }
            } catch (\Exception $e) {
                return 'Failed to upload file: ' . $e->getMessage();
            }
        } else {
            return 'No files uploaded.';
        }
    }
}
