<?php

namespace App\Controller;

use App\Entity\Offer;
use Doctrine\ORM\EntityManagerInterface;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class StripeWebhookController extends AbstractController
{
    #[Route('/api/stripe/webhook', methods: ['POST'])]
    public function handle(Request $request, EntityManagerInterface $em): Response
    {
        $payload = $request->getContent(); // ✅ brut
        $sigHeader = $request->headers->get('Stripe-Signature');

        $secret = $_SERVER['STRIPE_WEBHOOK_SECRET'] ?? $_ENV['STRIPE_WEBHOOK_SECRET'] ?? null;

        if (!$secret) return new Response('Webhook secret missing', 500);

        try {
            $event = Webhook::constructEvent($payload, $sigHeader, $secret);
        } catch (\UnexpectedValueException $e) {
            return new Response('Invalid payload', 400);
        } catch (SignatureVerificationException $e) {
            return new Response('Invalid signature', 400);
        }

        if ($event->type === 'payment_intent.succeeded') {
            /** @var \Stripe\PaymentIntent $pi */
            $pi = $event->data->object;

            $offerId = $pi->metadata['offer_id'] ?? null;
            if ($offerId) {
                $offer = $em->getRepository(Offer::class)->find((int)$offerId);
                if ($offer && $offer->getStatus() !== 'paid') {
                    $offer->setStatus('paid');
                    $offer->setPaidAt(new \DateTimeImmutable());
                    $em->flush();
                }
            }
        }

        // optionnel: payment_intent.payment_failed / canceled plus tard
        return new Response('ok', 200);
    }
}
