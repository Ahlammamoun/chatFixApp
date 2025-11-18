<?php

namespace App\Controller;

use App\Service\SiretValidator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class SiretTestController extends AbstractController
{
    private SiretValidator $validator;

    public function __construct(SiretValidator $validator)
    {
        $this->validator = $validator;
    }

    #[Route('/test/siret/{siret}', name: 'test_siret', methods: ['GET'])]
    public function testSiret(string $siret): JsonResponse
    {
        try {
            $result = $this->validator->checkSiret($siret);

            if (!$result['valid']) {
                return $this->json(['valid' => false, 'message' => $result['message']], 404);
            }

            $etab = $result['data'];
            $unite = $etab['uniteLegale'] ?? [];
            $adresse = $etab['adresseEtablissement'] ?? [];

            // 🧩 On simplifie la réponse
            return $this->json([
                'valid' => true,
                'siret' => $etab['siret'] ?? null,
                'nom' => $unite['denominationUniteLegale'] ?? null,
                'sigle' => $unite['sigleUniteLegale'] ?? null,
                'activite' => $unite['activitePrincipaleUniteLegale'] ?? null,
                'adresse' => trim(($adresse['numeroVoieEtablissement'] ?? '') . ' ' . ($adresse['libelleVoieEtablissement'] ?? '')),
                'codePostal' => $adresse['codePostalEtablissement'] ?? null,
                'ville' => $adresse['libelleCommuneEtablissement'] ?? null,
                'dateCreation' => $etab['dateCreationEtablissement'] ?? null,
            ]);
        } catch (\Throwable $e) {
            return $this->json([
                'valid' => false,
                'error' => 'Erreur interne lors de la vérification du SIRET',
                'details' => $e->getMessage(),
            ], 500);
        }
    }
}
