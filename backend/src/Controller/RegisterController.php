<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\Professional;
use App\Service\SiretValidator;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use App\Service\GeocodingService;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[AsController]
class RegisterController extends AbstractController
{
#[Route('/api/register', name: 'api_register', methods: ['POST'])]
public function register(
    Request $request,
    EntityManagerInterface $em,
    UserPasswordHasherInterface $passwordHasher,
    JWTTokenManagerInterface $jwtManager,
    GeocodingService $geocoder,
    ValidatorInterface $validator
): JsonResponse {

    $data = json_decode($request->getContent(), true);

    // 🔥 Vérification des champs obligatoires
    if (
        empty($data['email']) ||
        empty($data['password']) ||
        empty($data['postalCode']) ||
        empty($data['city'])
    ) {
        return new JsonResponse([
            'error' => 'Champs manquants',
            'missing' => ['email', 'password', 'postalCode', 'city']
        ], 400);
    }

    $email = $data['email'];
    $plainPassword = $data['password'];
    $role = $data['role'] ?? 'ROLE_USER';

    if ($em->getRepository(User::class)->findOneBy(['email' => $email])) {
        return new JsonResponse(['error' => 'Cet email est déjà utilisé'], 409);
    }

    // ➤ Géocoding utilisateur
    $fullAddress = $data['postalCode'] . ' ' . $data['city'];
    $coords = $geocoder->geocode($fullAddress);

    if (!$coords) {
        return new JsonResponse([
            'error' => 'Impossible de géocoder l’adresse utilisateur'
        ], 400);
    }

    // Création du user
    $user = new User();
    $user->setEmail($email);
    $user->setRoles([$role]);
    $user->setPassword($passwordHasher->hashPassword($user, $plainPassword));

    // On pourrait stocker latitude/longitude dans User si tu veux
    $user->setLatitude($coords['lat']);
    $user->setLongitude($coords['lng']);

    $em->persist($user);
    $em->flush();

    $token = $jwtManager->create($user);

    return new JsonResponse([
        'message' => 'Utilisateur créé avec succès',
        'token' => $token,
        'role' => $role
    ], 201);
}

}
