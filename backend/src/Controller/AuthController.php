<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class AuthController
{
    #[Route('/api/login', name: 'api_login', methods: ['POST'])]
    public function login(): JsonResponse
    {
        // Ce contrôleur ne sera jamais exécuté :
        // le firewall LexikJWT gère automatiquement cette route.
        return new JsonResponse(['message' => 'Handled by JWT system']);
    }
}
