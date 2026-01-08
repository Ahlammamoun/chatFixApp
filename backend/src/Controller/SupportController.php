<?php

namespace App\Controller;

use App\Repository\SupportTicketRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class SupportController extends AbstractController
{
    #[Route('/api/support/contact', methods: ['POST'])]
    public function contact(
        Request $request,
        SupportTicketRepository $repo
    ): JsonResponse {
        $data = json_decode($request->getContent(), true) ?? [];

        $email = trim((string)($data['email'] ?? ''));
        $subject = trim((string)($data['subject'] ?? ''));
        $message = trim((string)($data['message'] ?? ''));

        // -------------------
        // VALIDATION
        // -------------------
        if ($email === '' || !str_contains($email, '@')) {
            return $this->json(['error' => 'Email invalide'], 400);
        }
        if ($subject === '') {
            return $this->json(['error' => 'Objet obligatoire'], 400);
        }
        if ($message === '' || mb_strlen($message) < 5) {
            return $this->json(['error' => 'Message trop court'], 400);
        }

        // -------------------
        // SAVE VIA REPO
        // -------------------
        $repo->create($email, $subject, $message);

        return $this->json([
            'success' => true,
            'message' => 'Votre message a bien été transmis au support.'
        ], 201);
    }
}
