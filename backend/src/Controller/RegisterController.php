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
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\KernelInterface;


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
    ValidatorInterface $validator,
    KernelInterface $kernel
): JsonResponse {

    // ✅ multipart/form-data
    $data = $request->request->all();

    // 🔥 Vérification champs obligatoires
    $required = ['email', 'password', 'postalCode', 'city', 'name', 'lastname'];
    $missing = [];
    foreach ($required as $k) {
        if (empty($data[$k])) $missing[] = $k;
    }
    if ($missing) {
        return $this->json([
            'error' => 'Champs manquants',
            'missing' => $missing,
        ], Response::HTTP_BAD_REQUEST);
    }

    $email = trim($data['email']);
    $plainPassword = (string) $data['password'];
    $role = $data['role'] ?? 'ROLE_USER';

    if ($em->getRepository(User::class)->findOneBy(['email' => $email])) {
        return $this->json(['error' => 'Cet email est déjà utilisé'], Response::HTTP_CONFLICT);
    }

    // ✅ RIB : IBAN texte OU fichier
    $ribIban = $data['ribIban'] ?? null;
    /** @var UploadedFile|null $ribFile */
    $ribFile = $request->files->get('ribFile');

    // Si tu veux le rendre OBLIGATOIRE :
    if ((!$ribIban || trim((string)$ribIban) === '') && !$ribFile) {
        return $this->json([
            'error' => 'RIB requis',
            'violations' => [
                'ribIban' => ['Fournir un IBAN ou un fichier RIB.']
            ]
        ], Response::HTTP_BAD_REQUEST);
    }

    // ➤ Géocoding utilisateur
    $fullAddress = $data['postalCode'] . ' ' . $data['city'];
    $coords = $geocoder->geocode($fullAddress);

    if (!$coords) {
        return $this->json([
            'error' => 'Impossible de géocoder l’adresse utilisateur'
        ], Response::HTTP_BAD_REQUEST);
    }

    // ✅ Création user
    $user = new User();
    $user->setEmail($email);
    $user->setRoles([$role]);
    $user->setPassword($passwordHasher->hashPassword($user, $plainPassword));
    $user->setName($data['name']);
    $user->setLastname($data['lastname']);
    $user->setLatitude($coords['lat']);
    $user->setLongitude($coords['lng']);

    // ✅ IBAN si fourni
    if ($ribIban && trim((string)$ribIban) !== '') {
        $user->setRibIban(str_replace(' ', '', trim((string)$ribIban)));
    }

    // ✅ Upload fichier RIB si fourni
    if ($ribFile) {
        $uploadDir = $kernel->getProjectDir() . '/public/uploads/user_rib';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0775, true);
        }

        $ext = strtolower($ribFile->guessExtension() ?: $ribFile->getClientOriginalExtension() ?: 'bin');
        $safeExt = in_array($ext, ['pdf', 'png', 'jpg', 'jpeg'], true) ? $ext : 'bin';
        $name = bin2hex(random_bytes(16)) . '.' . $safeExt;

        $ribFile->move($uploadDir, $name);
        $user->setRibDoc('/uploads/user_rib/' . $name);
    }

    // ✅ Validation (IBAN etc.)
    $errors = $validator->validate($user);
    if (count($errors) > 0) {
        $violations = [];
        foreach ($errors as $e) {
            $violations[$e->getPropertyPath()][] = $e->getMessage();
        }
        return $this->json([
            'error' => 'Validation échouée',
            'violations' => $violations,
        ], Response::HTTP_BAD_REQUEST);
    }

    $em->persist($user);
    $em->flush();

    $token = $jwtManager->create($user);

    return $this->json([
        'message' => 'Utilisateur créé avec succès',
        'token' => $token,
        'role' => $role,
        'user' => [
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'name' => $user->getName(),
            'lastname' => $user->getLastname(),
            'lat' => $user->getLatitude(),
            'lng' => $user->getLongitude(),
            'ribIban' => $user->getRibIban(),
            'ribDoc' => $user->getRibDoc(),
        ],
    ], Response::HTTP_CREATED);
}
}
