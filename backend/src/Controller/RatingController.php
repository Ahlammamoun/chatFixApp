<?php

namespace App\Controller;

use App\Entity\Offer;
use App\Entity\Rating;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class RatingController extends AbstractController
{
    #[Route('/api/offers/{id}/rate', methods: ['POST'])]
    public function rateOffer(int $id, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $authUser = $this->getUser();
        if (!$authUser instanceof User) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        /** @var Offer|null $offer */
        $offer = $em->getRepository(Offer::class)->find($id);
        if (!$offer) {
            return $this->json(['error' => 'Offer not found'], 404);
        }

        // ✅ seul le client de l’offre
        if ($offer->getClient()?->getId() !== $authUser->getId()) {
            return $this->json(['error' => 'Forbidden'], 403);
        }

        // ✅ seulement si payé
        if ($offer->getStatus() !== 'paid') {
            return $this->json(['error' => 'Offer not paid'], 400);
        }

        $payload = $request->toArray();
        $value = isset($payload['value']) ? (float) $payload['value'] : 0;

        if ($value < 1 || $value > 5) {
            return $this->json(['error' => 'Rating must be between 1 and 5'], 400);
        }

        $professional = $offer->getProfessional();
        if (!$professional) {
            return $this->json(['error' => 'Professional missing on offer'], 400);
        }

        // ❌ déjà noté cette offre ?
        $existing = $em->getRepository(Rating::class)->findOneBy([
            'offer' => $offer,
            'user' => $authUser,
        ]);
        if ($existing) {
            return $this->json(['error' => 'Already rated for this offer'], 409);
        }

        $rating = new Rating();
        $rating->setOffer($offer);
        $rating->setUser($authUser);
        $rating->setProfessional($professional);
        $rating->setValue($value);

        $em->persist($rating);
        $em->flush();

        return $this->json([
            'success' => true,
            'message' => 'Merci pour votre évaluation',
            'value' => $value,
        ], 201);
    }
}
