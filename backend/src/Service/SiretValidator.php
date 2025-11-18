<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class SiretValidator
{
    private HttpClientInterface $httpClient;
    private string $apiKey;

    public function __construct(HttpClientInterface $httpClient, string $apiKey)
    {
        $this->httpClient = $httpClient;
        $this->apiKey = $apiKey;
    }

    public function checkSiret(string $siret): array
    {
        try {
            $response = $this->httpClient->request('GET', "https://api.insee.fr/api-sirene/3.11/siret/{$siret}", [
                'headers' => [
                    'X-INSEE-Api-Key-Integration' => $this->apiKey,
                    'Accept' => 'application/json',
                ],
                'timeout' => 5, // éviter le blocage
            ]);

            $data = $response->toArray(false);

            if (!isset($data['header']['statut']) || $data['header']['statut'] !== 200) {
                return [
                    'valid' => false,
                    'message' => $data['header']['message'] ?? "SIRET $siret introuvable ou invalide.",
                ];
            }

            return [
                'valid' => true,
                'data' => $data['etablissement'] ?? null,
                'message' => 'SIRET trouvé dans la base Sirene.',
            ];
        } catch (\Throwable $e) {
            return [
                'valid' => false,
                'message' => "Erreur de connexion à l’API INSEE : " . $e->getMessage(),
            ];
        }
    }
}
