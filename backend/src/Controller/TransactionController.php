<?php

namespace App\Controller;

use App\Entity\Offer;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Stripe\StripeClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class TransactionController extends AbstractController
{
    #[Route('/api/offers/{id}/transaction-summary', methods: ['GET'])]
    public function transactionSummary(
        int $id,
        EntityManagerInterface $em,
        StripeClient $stripe
    ): JsonResponse {
        $authUser = $this->getUser();
        if (!$authUser instanceof User) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        /** @var Offer|null $offer */
        $offer = $em->getRepository(Offer::class)->find($id);
        if (!$offer) {
            return $this->json(['error' => 'Offer not found'], 404);
        }

        $client = $offer->getClient(); // User
        $pro = $offer->getProfessional(); // Professional
        $proUser = $pro?->getUser(); // User

        $isClient = $client?->getId() === $authUser->getId();
        $isProUser = $proUser?->getId() === $authUser->getId();

        if (!$isClient && !$isProUser) {
            return $this->json(['error' => 'Forbidden'], 403);
        }

        // ✅ accessible uniquement si payé
        if ($offer->getStatus() !== 'paid' || $offer->getPaidAt() === null) {
            return $this->json(['error' => 'Offer not paid'], 400);
        }

        // (optionnel) infos Stripe
        $payment = null;
        if ($offer->getStripePaymentIntentId()) {
            $pi = $stripe->paymentIntents->retrieve($offer->getStripePaymentIntentId(), []);
            $payment = [
                'paymentIntentId' => $pi->id,
                'status' => $pi->status,
                'amountCents' => $pi->amount,
                'currency' => $pi->currency,
                'created' => $pi->created, // timestamp unix
            ];
        }

        return $this->json([
            'offer' => [
                'id' => $offer->getId(),
                'status' => $offer->getStatus(),
                'price' => $offer->getPrice(),
                'paidAt' => $offer->getPaidAt()?->format(DATE_ATOM),
            ],
            'payment' => $payment,
            'client' => [
                'id' => $client?->getId(),
                'name' => $client?->getName(),
                'lastname' => $client?->getLastname(),
                'email' => $client?->getEmail(),
                'lat' => $client?->getLatitude(),
                'lng' => $client?->getLongitude(),
            ],
            'professional' => [
                'id' => $pro?->getId(),
                'fullName' => $pro?->getFullName(),
                'companyName' => $pro?->getCompanyName(),
                'phoneNumber' => $pro?->getPhoneNumber(),
                'email' => $proUser?->getEmail(), // email du compte du pro
                'lat' => $pro?->getLatitude(),
                'lng' => $pro?->getLongitude(),
                'postalCode' => $pro?->getPostalCode(),
                'zone' => $pro?->getZone(),
                'speciality' => $pro?->getSpeciality()?->getName(),
                'profilePicture' => $pro?->getProfilePicture(),
            ],
        ]);
    }
}
