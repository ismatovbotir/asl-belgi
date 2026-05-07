<?php

namespace App\Services\AslBelgisi;

use App\Exceptions\AslBelgisiException;
use App\Models\ApiRequestLog;
use App\Models\Setting;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AslBelgisiClient
{
    protected string $baseUrl;
    protected string $apiKey;

    public function __construct()
    {
        $this->baseUrl = (string) (Setting::get('aslbelgisi_base_url') ?? config('aslbelgisi.base_url', ''));
        $this->apiKey  = (string) (Setting::get('aslbelgisi_api_key')  ?? config('aslbelgisi.api_key',  ''));
    }

    protected function businessRequest(string $method, string $path, array $data = []): array
    {
        $start = microtime(true);

        $response = Http::withToken($this->apiKey)
            ->withHeaders(['Content-Type' => 'application/json;charset=UTF-8'])
            ->timeout(config('aslbelgisi.timeout'))
            ->retry(config('aslbelgisi.retry_times'), config('aslbelgisi.retry_sleep'))
            ->{strtolower($method)}($this->baseUrl . $path, $data);

        $duration = (int) ((microtime(true) - $start) * 1000);
        $this->logRequest($method, $path, $data, $response, $duration);

        if ($response->failed()) {
            $this->handleError($response);
        }

        return $response->json() ?? [];
    }

    protected function handleError(Response $response): void
    {
        $errors      = $response->json();
        $errorId     = data_get($errors, '0.errorId', data_get($errors, 'errorId', 'unknown'));
        $description = data_get($errors, '0.context.description', data_get($errors, 'message', $response->body()));

        Log::channel('aslbelgisi')->error("API Error [{$response->status()}]: $description", [
            'errorId' => $errorId,
            'status'  => $response->status(),
            'body'    => $errors,
        ]);

        throw new AslBelgisiException($description, $response->status());
    }

    private function logRequest(string $method, string $path, array $data, Response $response, int $durationMs): void
    {
        Log::channel('aslbelgisi')->info("$method $path [{$response->status()}] {$durationMs}ms");

        try {
            ApiRequestLog::create([
                'method'          => strtoupper($method),
                'endpoint'        => $path,
                'request_body'    => empty($data) ? null : $data,
                'response_status' => $response->status(),
                'response_body'   => $response->json(),
                'error_id'        => $response->failed() ? data_get($response->json(), '0.errorId') : null,
                'duration_ms'     => $durationMs,
            ]);
        } catch (\Throwable) {
            // DB logging is best-effort
        }
    }
}
