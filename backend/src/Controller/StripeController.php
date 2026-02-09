<?php

namespace App\Controller;

use App\Entity\Offer;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Stripe\StripeClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class StripeController extends AbstractController
{
    #[Route('/api/offers/{id}/payment-intent', methods: ['POST'])]
    public function createPaymentIntentForOffer(
        int $id,
        EntityManagerInterface $em,
        StripeClient $stripe
    ): JsonResponse {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        /** @var Offer|null $offer */
        $offer = $em->getRepository(Offer::class)->find($id);
        if (!$offer) {
            return $this->json(['error' => 'Offer not found'], 404);
        }

        // ✅ seul le client de l’offre peut payer
        if ($offer->getClient()->getId() !== $user->getId()) {
            return $this->json(['error' => 'Forbidden'], 403);
        }

        // ✅ il faut que l’offre soit acceptée avant paiement
        if ($offer->getStatus() !== 'accepted') {
            return $this->json(['error' => 'Offer must be accepted before paying'], 400);
        }

        // ✅ déjà payé ?
        if ($offer->getStatus() === 'paid' || $offer->getPaidAt() !== null) {
            return $this->json(['error' => 'Already paid'], 400);
        }

        $amountCents = (int) round($offer->getPrice() * 100);
        if ($amountCents <= 0) {
            return $this->json(['error' => 'Invalid amount'], 400);
        }

        // ✅ si PaymentIntent déjà créé, on le relit et on renvoie le client_secret
        if ($offer->getStripePaymentIntentId()) {
            $pi = $stripe->paymentIntents->retrieve($offer->getStripePaymentIntentId(), []);

            // (optionnel) si déjà réussi (normalement webhook gère, mais ça aide)
            if ($pi->status === 'succeeded') {
                $offer->setStatus('paid');
                $offer->setPaidAt(new \DateTimeImmutable());
                $em->flush();

                return $this->json(['status' => 'paid']);
            }

            return $this->json([
                'paymentIntentId' => $pi->id,
                'clientSecret' => $pi->client_secret,
                'status' => $pi->status,
            ]);
        }

        // ✅ création PaymentIntent
        $pi = $stripe->paymentIntents->create([
            'amount' => $amountCents,
            'currency' => 'eur',
            'automatic_payment_methods' => ['enabled' => true],
            'metadata' => [
                'offer_id' => (string) $offer->getId(),
                'client_id' => (string) $offer->getClient()->getId(),
                'professional_id' => (string) $offer->getProfessional()->getId(),
            ],
        ]);

        $offer->setStripePaymentIntentId($pi->id);
        $em->flush();

        return $this->json([
            'paymentIntentId' => $pi->id,
            'clientSecret' => $pi->client_secret,
            'status' => $pi->status,
        ], Response::HTTP_CREATED);
    }

    
}
