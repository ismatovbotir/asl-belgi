<?php

namespace App\Http\Controllers\AslBelgisi;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\AslBelgisi\Auth\BusinessAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class AuthController extends Controller
{
    private const ENCRYPTED_KEYS = ['aslbelgisi_api_key'];

    public function __construct(
        private BusinessAuthService $businessAuth,
    ) {}

    public function settings()
    {
        $current = [
            'base_url' => Setting::get('aslbelgisi_base_url', config('aslbelgisi.base_url')),
            'api_key'  => Setting::get('aslbelgisi_api_key',  config('aslbelgisi.api_key')),
            'tin'      => Setting::get('aslbelgisi_tin',      config('aslbelgisi.tin')),
            'timeout'  => Setting::get('aslbelgisi_timeout',  config('aslbelgisi.timeout', 30)),
        ];

        $source     = Setting::has('aslbelgisi_api_key') ? 'database' : 'env';
        $configured = ! empty($current['api_key']) && ! empty($current['tin']);

        return view('aslbelgisi.settings', compact('current', 'source', 'configured'));
    }

    public function saveCredentials(Request $request)
    {
        $request->validate([
            'base_url' => 'required|url',
            'tin'      => 'required|string|max:50',
            'timeout'  => 'nullable|integer|min:5|max:120',
        ]);

        Setting::set('aslbelgisi_base_url', $request->input('base_url'));
        Setting::set('aslbelgisi_tin',      $request->input('tin'));
        Setting::set('aslbelgisi_timeout',  $request->input('timeout', 30));

        if ($request->filled('api_key')) {
            Setting::set('aslbelgisi_api_key', $request->input('api_key'), encrypted: true);
        }

        return back()->with('success', 'Credentials saved to database.');
    }

    public function testCredentials(Request $request): JsonResponse
    {
        $baseUrl = rtrim((string) $request->input('base_url', ''), '/');
        $tin     = (string) $request->input('tin', '');
        $apiKey  = $request->filled('api_key')
            ? $request->input('api_key')
            : (string) (Setting::get('aslbelgisi_api_key') ?? '');

        if (! $baseUrl || ! $tin || ! $apiKey) {
            return response()->json(['success' => false, 'message' => 'Base URL, TIN and API Key are required.']);
        }

        try {
            $response = Http::withToken($apiKey)
                ->withHeaders(['Content-Type' => 'application/json;charset=UTF-8'])
                ->timeout(10)
                ->get($baseUrl . '/public/api/v1/party/parties/' . $tin . '/api-keys/check');

            if ($response->failed()) {
                $body = $response->json();
                $msg  = data_get($body, '0.context.description', data_get($body, 'message', 'HTTP ' . $response->status()));
                return response()->json(['success' => false, 'message' => $msg]);
            }

            $result = $response->json();
            $msg    = 'Connection OK — TIN valid: ' . ($result['isTinCorrect'] ? 'Yes' : 'No');
            if (! empty($result['expiresOn'])) {
                $msg .= ' | Key expires: ' . $result['expiresOn'];
            }
            return response()->json(['success' => true, 'message' => $msg]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function checkKey()
    {
        $tin    = Setting::get('aslbelgisi_tin', config('aslbelgisi.tin'));
        $apiKey = Setting::get('aslbelgisi_api_key', config('aslbelgisi.api_key'));

        if (! $tin || ! $apiKey) {
            return back()->with('error', 'TIN and API Key must be configured first.');
        }

        try {
            $result = $this->businessAuth->checkKey($tin);
            $msg    = 'Connection OK — TIN valid: ' . ($result['isTinCorrect'] ? 'Yes' : 'No');
            if (! empty($result['expiresOn'])) {
                $msg .= ' | Key expires: ' . $result['expiresOn'];
            }
            return back()->with('success', $msg);
        } catch (\Throwable $e) {
            return back()->with('error', 'Connection failed: ' . $e->getMessage());
        }
    }

    public function refreshKey()
    {
        $tin = Setting::get('aslbelgisi_tin', config('aslbelgisi.tin'));
        $key = Setting::get('aslbelgisi_api_key', config('aslbelgisi.api_key'));

        try {
            $result  = $this->businessAuth->refreshKey($tin, $key);
            $newKey  = $result['apiKey'] ?? null;
            if ($newKey) {
                Setting::set('aslbelgisi_api_key', $newKey, encrypted: true);
                return back()->with('success', 'API key refreshed and saved to database. Expires: ' . ($result['expiresOn'] ?? 'N/A'));
            }
            return back()->with('warning', 'Refresh call succeeded but no new key returned. Response: ' . json_encode($result));
        } catch (\Throwable $e) {
            return back()->with('error', 'Refresh failed: ' . $e->getMessage());
        }
    }
}
