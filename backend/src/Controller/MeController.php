<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\Professional;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class MeController extends AbstractController
{
    #[Route('/api/me', name: 'api_me', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function __invoke(EntityManagerInterface $em): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user) {
            return $this->json(['error' => 'Utilisateur non connecté'], 401);
        }

        // 🔹 Récupération du rôle principal
        $roles = $user->getRoles();
        $role = in_array('ROLE_PROFESSIONAL', $roles) ? 'professional' : 'user';

        // 🔹 Données de base utilisateur
        $data = [
            'email' => $user->getEmail(),
            'role' => $role,
        ];

        // 🔹 Si professionnel, inclure les infos du profil associé
        if ($role === 'professional') {
            $pro = $em->getRepository(Professional::class)->findOneBy(['user' => $user]);
            if ($pro) {
                $data['professional'] = [
                    'id' => $pro->getId(),
                    'fullName' => $pro->getFullName(),
                    'speciality' => $pro->getSpeciality()?->getName(),
                    'zone' => $pro->getZone(),
                    'pricePerHour' => $pro->getPricePerHour(),
                    'phoneNumber' => $pro->getPhoneNumber(),
                    'companyName' => $pro->getCompanyName(),
                    'profilePicture' => $pro->getProfilePicture(),
                ];
            }
        }

        return $this->json([
            'success' => true,
            'user' => $data,
        ]);
    }
}
