<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\Professional;
use App\Entity\Speciality;
use App\Service\SiretValidator;
use App\Service\GeocodingService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{JsonResponse, Request, Response};
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use App\Repository\ProfessionalRepository;

class ProfessionalController extends AbstractController
{
    #[Route('/api/professionals', name: 'create_professional', methods: ['POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $em,
        ValidatorInterface $validator,
        SiretValidator $siretValidator,
        GeocodingService $geocoder,
        UserPasswordHasherInterface $passwordHasher
    ): JsonResponse {

        $data = json_decode($request->getContent(), true) ?? [];

        // Champs obligatoires
        if (
            empty($data['email']) ||
            empty($data['password']) ||
            empty($data['specialityId']) ||
            empty($data['phone']) ||
            empty($data['postalCode']) ||
            empty($data['zone'])
        ) {
            return $this->json([
                'error' => 'Champs manquants',
                'violations' => [
                    'email' => ['Email requis.'],
                    'password' => ['Mot de passe requis.'],
                    'specialityId' => ['Spécialité requise.'],
                    'phone' => ['Téléphone requis.'],
                    'postalCode' => ['Code postal requis.'],
                    'zone' => ['Ville / zone requise.'],
                ]
            ], Response::HTTP_BAD_REQUEST);
        }

        // Vérifier si email existe
        if ($em->getRepository(User::class)->findOneBy(['email' => $data['email']])) {
            return $this->json([
                'error' => 'Cet email est déjà utilisé.'
            ], Response::HTTP_CONFLICT);
        }

        // Récupération de la spécialité
        $speciality = $em->getRepository(Speciality::class)->find($data['specialityId']);
        if (!$speciality) {
            return $this->json(['error' => 'Spécialité invalide'], Response::HTTP_BAD_REQUEST);
        }

        // Création du User
        $user = new User();
        $user->setEmail($data['email']);
        $user->setRoles(['ROLE_PROFESSIONAL']);
        $user->setPassword($passwordHasher->hashPassword($user, $data['password']));

        // Création du Professional
        $pro = new Professional();
        $pro->setFullName($data['fullName'] ?? '');
        $pro->setSpeciality($speciality);
        $pro->setDescription($data['description'] ?? '');
        $pro->setZone($data['zone']);
        $pro->setPostalCode($data['postalCode']);
        $pro->setPricePerHour((float)($data['pricePerHour'] ?? 0));
        $pro->setAvailability(true);
        $pro->setSiret($data['siret'] ?? '');
        $pro->setPhoneNumber($data['phone']);
        $pro->setUser($user);

        // Vérif SIRET
        $sirene = $siretValidator->checkSiret($pro->getSiret());
        if (!$sirene['valid']) {
            return $this->json([
                'error' => 'SIRET invalide',
                'details' => $sirene['message'] ?? 'Erreur SIRENE'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Nom d’entreprise issu de Sirene uniquement
        $entreprise = $sirene['data']['uniteLegale']['denominationUniteLegale'] ?? null;
        $pro->setCompanyName($entreprise);

        // 🌍 GEOCODING POUR OBTENIR LAT / LNG
        $fullAddress = $pro->getPostalCode() . ' ' . $pro->getZone();
        $coords = $geocoder->geocode($fullAddress);

        if ($coords) {
            $pro->setLatitude($coords['lat']);
            $pro->setLongitude($coords['lng']);
        } else {
            return $this->json([
                'error' => 'Impossible de géocoder le code postal + la ville fournis.'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Validation
        $errors = $validator->validate($pro);
        if (count($errors) > 0) {
            $violations = [];
            foreach ($errors as $e) {
                $violations[$e->getPropertyPath()][] = $e->getMessage();
            }

            return $this->json([
                'error' => 'Validation échouée',
                'violations' => $violations
            ], Response::HTTP_BAD_REQUEST);
        }

        // Save
        $em->persist($user);
        $em->persist($pro);
        $em->flush();

        return $this->json([
            'message' => 'Inscription professionnelle réussie',
            'professional' => [
                'id' => $pro->getId(),
                'email' => $user->getEmail(),
                'fullName' => $pro->getFullName(),
                'speciality' => $speciality->getName(),
                'siret' => $pro->getSiret(),
                'companyName' => $pro->getCompanyName(),
                'phone' => $pro->getPhoneNumber(),
                'postalCode' => $pro->getPostalCode(),
                'lat' => $pro->getLatitude(),
                'lng' => $pro->getLongitude(),
            ]
        ], Response::HTTP_CREATED);
    }

    #[Route('/api/professionals/{id}/upload', name: 'upload_profile_picture', methods: ['POST'])]
    public function uploadProfilePicture(
        int $id,
        Request $request,
        EntityManagerInterface $em
    ): JsonResponse {
        $pro = $em->getRepository(Professional::class)->find($id);
        if (!$pro) {
            return $this->json(['error' => 'Professionnel introuvable'], 404);
        }

        $file = $request->files->get('file');
        if (!$file) {
            return $this->json(['error' => 'Aucun fichier reçu'], 400);
        }

        if (!in_array($file->guessExtension(), ['jpg', 'jpeg', 'png', 'webp'])) {
            return $this->json(['error' => 'Format de fichier invalide'], 400);
        }

        // ✅ Renomme et déplace le fichier
        $newFilename = uniqid('pro_') . '.' . $file->guessExtension();
        $file->move($this->getParameter('kernel.project_dir') . '/public/uploads/profiles', $newFilename);

        $pro->setProfilePicture('/uploads/profiles/' . $newFilename);
        $em->flush();

        return $this->json([
            'message' => '✅ Photo mise à jour avec succès',
            'profilePicture' => $pro->getProfilePicture(),
        ]);
    }


    #[Route('/api/professionals/search', name: 'api_professionals_search', methods: ['GET'])]
    public function search(ProfessionalRepository $repo, Request $request): JsonResponse
    {
        $speciality = $request->query->get('speciality');
        $zone = $request->query->get('zone');
        $query = $request->query->get('query');

        // Convertir lat/lng en float (sinon l’Haversine casse)
        $lat = $request->query->get('lat');
        $lng = $request->query->get('lng');

        $lat = $lat !== null ? floatval($lat) : null;
        $lng = $lng !== null ? floatval($lng) : null;

        // Appel repo
        $pros = $repo->searchProfessionals($speciality, $zone, $query, $lat, $lng);

        // Format JSON
        $data = array_map(function ($pro) {
            return [
                'id'            => $pro['id'],
                'fullName'      => $pro['full_name'],
                'speciality'    => $pro['speciality'],
                'description'   => $pro['description'],
                'zone'          => $pro['zone'],
                'pricePerHour'  => floatval($pro['price_per_hour']),
                'availability'  => (bool)$pro['availability'],
                'companyName'   => $pro['company_name'],
                'phoneNumber'   => $pro['phone_number'],
                'profilePicture' => $pro['profile_picture'],
                'distance'      => isset($pro['distance']) ? round(floatval($pro['distance']), 1) : null,
            ];
        }, $pros);

        return $this->json($data);
    }



    #[Route('/specialities', name: 'api_specialities_list', methods: ['GET'])]
    public function listSpecialities(\App\Repository\SpecialityRepository $repo): JsonResponse
    {
        $specialities = $repo->findAll();

        $data = array_map(fn($spec) => [
            'id' => $spec->getId(),
            'name' => $spec->getName(),
        ], $specialities);

        return $this->json($data);
    }
}
