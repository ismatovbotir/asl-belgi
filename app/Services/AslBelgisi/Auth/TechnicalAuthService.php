<?php

namespace App\Services\AslBelgisi\Auth;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class TechnicalAuthService
{
    private const CACHE_KEY   = 'aslbelgisi_access_token';
    private const REFRESH_KEY = 'aslbelgisi_refresh_token';
    private const CACHE_TTL   = 25 * 60;       // 25 min (token valid 30 min)
    private const REFRESH_TTL = 23 * 60 * 60;  // 23 hours

    public function getAccessToken(): string
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, fn () => $this->authenticate());
    }

    private function authenticate(): string
    {
        $baseUrl  = Setting::get('aslbelgisi_base_url',       config('aslbelgisi.base_url'));
        $login    = Setting::get('aslbelgisi_tech_login',    config('aslbelgisi.tech_login'));
        $password = Setting::get('aslbelgisi_tech_password', config('aslbelgisi.tech_password'));

        $response = Http::withHeaders(['Content-Type' => 'application/json;charset=UTF-8'])
            ->post($baseUrl . '/api/users/authenticate', [
                'login'    => $login,
                'password' => $password,
            ]);

        if ($response->failed()) {
            throw new \RuntimeException('Tech user auth failed: ' . $response->body());
        }

        Cache::put(self::REFRESH_KEY, $response->json('refreshToken'), self::REFRESH_TTL);

        return $response->json('accessToken');
    }

    public function forgetToken(): void
    {
        Cache::forget(self::CACHE_KEY);
        Cache::forget(self::REFRESH_KEY);
    }
}
