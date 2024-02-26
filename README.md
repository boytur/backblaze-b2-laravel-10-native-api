# How to upload image file to backblaze-b2 with native api in laravel10

- Thank you for the concepts https://grantwinney.com/what-is-backblaze-b2-api

- Docs https://www.backblaze.com/apidocs/introduction-to-the-b2-native-api

<img src="https://f005.backblazeb2.com/file/piyawatdev/b2.jpg"/>

### 1. Install Laravel project

```
composer create-project laravel/laravel example-app

cd example-app
```

### 2. Install guzzlehttp

```
composer require guzzlehttp/guzzle
```

### 3. In yout ENV file 

```
B2_ACCOUNT_ID = 
B2_APPLICATION_KEY = 
B2_BUCKET_NAME = 
B2_BUCKET_ID = 
B2_API = https://api.backblazeb2.com
```
### 4 Crate folder Commands in app/console
<img src="https://f005.backblazeb2.com/file/piyawatdev/%E0%B8%AA%E0%B8%81%E0%B8%A3%E0%B8%B5%E0%B8%99%E0%B8%8A%E0%B9%87%E0%B8%AD%E0%B8%95+2024-02-26+224423.png"/>

### 5. Create command to refresh the token

```
php artisan make:command RefreshB2Token
```
<img src="https://f005.backblazeb2.com/file/piyawatdev/%E0%B8%AA%E0%B8%81%E0%B8%A3%E0%B8%B5%E0%B8%99%E0%B8%8A%E0%B9%87%E0%B8%AD%E0%B8%95+2024-02-26+224830.png"/>

### 6. In your RefreshB2Token.php

```
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

```
### 7.  Create schedule for refresh your token in app\Console\Kernel.php

```
<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('b2:refresh-token')->hourly();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}

```

### 8. Create controller for testig
```
php artisan make:controller FileUploadController
```


### 9. In your FileUploadController.php
```
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

```

### 10. Edit your view welcome.blade.php

```
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>File Upload</title>
</head>
<body>
    <h1>File Upload</h1>
    <form id="uploadForm" action="/upload" method="post" enctype="multipart/form-data">
        @csrf
        <input type="file" name="file">
        <button type="submit">Upload</button>
    </form>
    <div id="response"></div>
</body>
</html>

```
### 11. Edit your route in web.php

```
<?php
use App\Http\Controllers\FileUploadController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::post('/upload', [FileUploadController::class, 'uploadToB2']);

```
### 12. Testing create your token
> You can using dd($uploadUrl,$uploadUrlAuthorizationToken) in your FileUploadController.php for sure.

```
php artisan b2:refresh-token
```

### The result 

<img src="https://f005.backblazeb2.com/file/piyawatdev/example.gif"/>

#### In my backblaze-b2
<img src="https://f005.backblazeb2.com/file/piyawatdev/%E0%B8%AA%E0%B8%81%E0%B8%A3%E0%B8%B5%E0%B8%99%E0%B8%8A%E0%B9%87%E0%B8%AD%E0%B8%95+2024-02-26+230505.png"/>
