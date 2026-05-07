<?php

namespace App\Services\AslBelgisi\Auth;

use App\Services\AslBelgisi\AslBelgisiClient;

class BusinessAuthService extends AslBelgisiClient
{
    public function checkKey(string $tin): array
    {
        // Response: { "isTinCorrect": bool, "expiresOn": "2025-11-15T23:59:59Z" }
        return $this->businessRequest('GET', "/public/api/v1/party/parties/{$tin}/api-keys/check");
    }

    public function refreshKey(string $tin, string $apiKeyOrId, bool $isId = false): array
    {
        $body = $isId ? ['id' => $apiKeyOrId] : ['apiKey' => $apiKeyOrId];
        // Response: { "apiKey": "new-uuid", "id": "...", "expiresOn": "...", "label": "..." }
        return $this->businessRequest('POST', "/public/api/v1/party/parties/{$tin}/api-keys/refresh", $body);
    }
}
