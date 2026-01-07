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
use App\Entity\Message;
use App\Entity\Offer;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use App\Repository\MessageRepository;
use Symfony\Component\HttpKernel\KernelInterface;
use Psr\Log\LoggerInterface;



class ProfessionalController extends AbstractController
{
    #[Route('/api/professionals', name: 'create_professional', methods: ['POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $em,
        ValidatorInterface $validator,
        SiretValidator $siretValidator,
        GeocodingService $geocoder,
        UserPasswordHasherInterface $passwordHasher,
        KernelInterface $kernel,
        LoggerInterface $logger,

    ): JsonResponse {

        // ✅ multipart/form-data
        $data = $request->request->all();


        // Champs obligatoires (texte)
        if (
            empty($data['email']) ||
            empty($data['password']) ||
            empty($data['specialityId']) ||
            empty($data['phone']) ||
            empty($data['postalCode']) ||
            empty($data['zone'])
        ) {
            return $this->json(['error' => 'Champs manquants'], Response::HTTP_BAD_REQUEST);
        }

    // ✅ documents obligatoires
        /** @var UploadedFile|null $assurance */
        $assurance = $request->files->get('assurance');
        /** @var UploadedFile|null $identity */
        $identity = $request->files->get('identity');
        /** @var UploadedFile|null $proTitle */
        $proTitle = $request->files->get('proTitle');

        if (!$assurance || !$identity || !$proTitle) {
            return $this->json([
                'error' => 'Documents manquants',
                'violations' => [
                    'assurance' => !$assurance ? ['Assurance requise.'] : [],
                    'identity'  => !$identity ? ['Pièce d’identité requise.'] : [],
                    'proTitle'  => !$proTitle ? ['Titre pro requis.'] : [],
                ]
            ], Response::HTTP_BAD_REQUEST);
        }

        // RIB: soit texte, soit fichier
        $ribIban = $data['ribIban'] ?? null;
        /** @var UploadedFile|null $ribFile */
        $ribFile = $request->files->get('ribFile');

        if (!$ribIban && !$ribFile) {
            return $this->json([
                'error' => 'RIB requis',
                'violations' => [
                    'ribIban' => ['Fournir un IBAN ou un fichier RIB.']
                ]
            ], Response::HTTP_BAD_REQUEST);
        }

        // Vérifier si email existe
        if ($em->getRepository(User::class)->findOneBy(['email' => $data['email']])) {
            return $this->json(['error' => 'Cet email est déjà utilisé.'], Response::HTTP_CONFLICT);
        }

        // Spécialité
        $speciality = $em->getRepository(Speciality::class)->find($data['specialityId']);
        if (!$speciality) {
            return $this->json(['error' => 'Spécialité invalide'], Response::HTTP_BAD_REQUEST);
        }

        // Création user
        $user = new User();
        $user->setEmail($data['email']);
        $user->setRoles(['ROLE_PROFESSIONAL']);
        $user->setPassword($passwordHasher->hashPassword($user, $data['password']));

        // Création pro
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

        // RIB texte (si fourni)
        if ($ribIban) {
            $pro->setRibIban(str_replace(' ', '', $ribIban));
        }

        // Vérif SIRET
        $sirene = $siretValidator->checkSiret($pro->getSiret());
        if (!$sirene['valid']) {
            return $this->json([
                'error' => 'SIRET invalide',
                'details' => $sirene['message'] ?? 'Erreur SIRENE'
            ], Response::HTTP_BAD_REQUEST);
        }

        $entreprise = $sirene['data']['uniteLegale']['denominationUniteLegale'] ?? null;
        $pro->setCompanyName($entreprise);

        // Geocoding
        $fullAddress = $pro->getPostalCode() . ' ' . $pro->getZone();
        $coords = $geocoder->geocode($fullAddress);
        if (!$coords) {
            return $this->json(['error' => 'Impossible de géocoder le code postal + la ville fournis.'], Response::HTTP_BAD_REQUEST);
        }
        $pro->setLatitude($coords['lat']);
        $pro->setLongitude($coords['lng']);
        $user->setLatitude($coords['lat']);
        $user->setLongitude($coords['lng']);

        // ✅ Upload fichiers
        $uploadDir = $kernel->getProjectDir() . '/public/uploads/professional_docs';

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0775, true);
        }

        $saveUpload = function (UploadedFile $file) use ($uploadDir): string {
            // sécurité basique + nom unique
            $ext = strtolower($file->guessExtension() ?: $file->getClientOriginalExtension() ?: 'bin');
            $safeExt = in_array($ext, ['pdf', 'png', 'jpg', 'jpeg'], true) ? $ext : 'bin';
            $name = bin2hex(random_bytes(16)) . '.' . $safeExt;
            $file->move($uploadDir, $name);
            return '/uploads/professional_docs/' . $name; // chemin public
        };

        $pro->setAssuranceDoc($saveUpload($assurance));
        $pro->setIdentityDoc($saveUpload($identity));
        $pro->setProTitleDoc($saveUpload($proTitle));

        if ($ribFile) {
            $pro->setRibDoc($saveUpload($ribFile));
        }

        // Validation entity (inclut IBAN si présent)
        $errors = $validator->validate($pro);
        if (count($errors) > 0) {
            $violations = [];
            foreach ($errors as $e) {
                $violations[$e->getPropertyPath()][] = $e->getMessage();
            }
            return $this->json(['error' => 'Validation échouée', 'violations' => $violations], Response::HTTP_BAD_REQUEST);
        }

        $em->persist($user);
        $em->persist($pro);
        $em->flush();

        return $this->json(
            [
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
                    'assuranceDoc' => $pro->getAssuranceDoc(),
                    'identityDoc' => $pro->getIdentityDoc(),
                    'proTitleDoc' => $pro->getProTitleDoc(),
                    'ribIban' => $pro->getRibIban(),
                    'ribDoc' => $pro->getRibDoc(),
                ]
            ],
            Response::HTTP_CREATED
        );
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

        // 2️⃣ Si pas envoyé -> on prend la position du user stockée en BDD
        if ($lat === null || $lng === null) {
            $user = $this->getUser();  // user connecté via JWT

            // Vérifier que c'est bien TON entité User (et non l'interface)
            if ($user instanceof \App\Entity\User) {

                if ($user->getLatitude() !== null && $user->getLongitude() !== null) {
                    $lat = $user->getLatitude();
                    $lng = $user->getLongitude();
                }
            }
        }

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

    #[Route('/api/messages', methods: ['POST'])]
    public function sendMessage(
        Request $request,
        EntityManagerInterface $em
    ): JsonResponse {

        // 🔐 Utilisateur connecté (sender)
        $sender = $this->getUser();
        if (!$sender instanceof User) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        // 📥 Payload
        $data = json_decode($request->getContent(), true);

        if (
            !isset($data['professional_id']) ||
            !isset($data['content']) ||
            empty(trim($data['content']))
        ) {
            return $this->json(['error' => 'Invalid payload'], 400);
        }

        // 👨‍🔧 Récupération du PROFESSIONAL
        $professional = $em->getRepository(Professional::class)
            ->find((int) $data['professional_id']);

        if (!$professional instanceof Professional) {
            return $this->json([
                'error' => 'Professional not found',
                'professional_id' => $data['professional_id']
            ], 404);
        }

        // 👤 Récupération du USER du professionnel
        $receiver = $professional->getUser();

        if (!$receiver instanceof User) {
            return $this->json([
                'error' => 'User linked to professional not found'
            ], 404);
        }

        // ✉️ Création du message
        $message = new Message();
        $message->setSender($sender);
        $message->setReceiver($receiver);
        $message->setContent(trim($data['content']));
        $message->setCreatedAt(new \DateTimeImmutable());

        // 💾 Sauvegarde
        $em->persist($message);
        $em->flush();

        return $this->json(['success' => true], 200);
    }


    #[Route('/api/messages/{professionalId}', methods: ['GET'])]
    public function getConversation(
        int $professionalId,
        EntityManagerInterface $em,
        MessageRepository $messageRepo
    ): JsonResponse {

        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        // 🔍 Pro
        $professional = $em->getRepository(Professional::class)
            ->find($professionalId);

        if (!$professional) {
            return $this->json(['error' => 'Professional not found'], 404);
        }

        $receiver = $professional->getUser();
        if (!$receiver instanceof User) {
            return $this->json(['error' => 'User not found'], 404);
        }

        // 📩 Messages
        $messages = $messageRepo->findConversation(
            $user->getId(),
            $receiver->getId()
        );

        // ✅ Marquer comme lus
        $messageRepo->markAsRead(
            $user->getId(),
            $receiver->getId()
        );

        // 💼 Offres envoyées par l'utilisateur à ce pro
        $offers = $em->getRepository(Offer::class)->findBy(
            [
                'client' => $user,
                'professional' => $professional
            ],
            ['createdAt' => 'ASC']
        );

        // 🔀 Fusion messages + offres
        $conversation = [];

        foreach ($messages as $m) {
            $conversation[] = [
                'type' => 'message',
                'id' => $m->getId(),
                'content' => $m->getContent(),
                'isMine' => $m->getSender()->getId() === $user->getId(),
                'isRead' => $m->isRead(),
                'createdAt' => $m->getCreatedAt()->getTimestamp(),
            ];
        }

        foreach ($offers as $o) {
            $conversation[] = [
                'type' => 'offer',
                'id' => $o->getId(),
                'price' => $o->getPrice(),
                'status' => $o->getStatus(),
                'message' => $o->getMessage(),
                'isMine' => true,
                'createdAt' => $o->getCreatedAt()->getTimestamp(),
            ];
        }

        // 🕒 Tri chronologique
        usort($conversation, fn($a, $b) => $a['createdAt'] <=> $b['createdAt']);

        return $this->json($conversation, 200);
    }


    #[Route('/api/pro/messages', methods: ['GET'])]
    public function proInbox(EntityManagerInterface $em, MessageRepository $repo): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        $pro = $em->getRepository(Professional::class)->findOneBy(['user' => $user]);
        if (!$pro) {
            return $this->json(['error' => 'Professional not found'], 404);
        }

        // ✅ Ici on passe l'id du User du pro
        $threads = $repo->findProThreads($user->getId());

        return $this->json($threads, 200);
    }



    #[Route('/api/user/messages', methods: ['GET'])]
    public function userInbox(EntityManagerInterface $em, MessageRepository $repo): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        // Threads pour l’utilisateur connecté
        $threads = $repo->findUserThreads($user->getId());

        return $this->json($threads, 200);
    }









    #[Route('/api/pro/messages/{clientId}', methods: ['GET'])]
    public function proConversation(
        int $clientId,
        EntityManagerInterface $em,
        MessageRepository $messageRepo
    ): JsonResponse {
        $user = $this->getUser();
        if (!$user instanceof User) return $this->json(['error' => 'Unauthorized'], 401);

        $pro = $em->getRepository(Professional::class)->findOneBy(['user' => $user]);
        if (!$pro) return $this->json(['error' => 'Professional not found'], 404);

        $client = $em->getRepository(User::class)->find($clientId);
        if (!$client) return $this->json(['error' => 'Client not found'], 404);

        $messages = $messageRepo->findConversation($client->getId(), $user->getId());

        // ✅ marquer comme lu (messages envoyés par le client au pro)
        $messageRepo->markAsRead($client->getId(), $user->getId());

        $conversation = [];
        foreach ($messages as $m) {
            $conversation[] = [
                'type' => 'message',
                'id' => $m->getId(),
                'content' => $m->getContent(),
                'isMine' => $m->getSender()->getId() === $user->getId(), // pro
                'isRead' => $m->isRead(),
                'createdAt' => $m->getCreatedAt()->getTimestamp(),
            ];
        }

        return $this->json($conversation, 200);
    }



    #[Route('/api/pro/messages/{clientId}', methods: ['POST'])]
    public function proSendMessage(
        int $clientId,
        Request $request,
        EntityManagerInterface $em
    ): JsonResponse {
        $user = $this->getUser();
        if (!$user instanceof User) return $this->json(['error' => 'Unauthorized'], 401);

        $client = $em->getRepository(User::class)->find($clientId);
        if (!$client) return $this->json(['error' => 'Client not found'], 404);

        $data = json_decode($request->getContent(), true) ?? [];
        if (empty($data['content'])) return $this->json(['error' => 'Content required'], 400);

        $msg = new Message();
        $msg->setSender($user);      // ✅ pro user
        $msg->setReceiver($client);  // ✅ client
        $msg->setContent($data['content']);
        $msg->setIsRead(false);
        $msg->setCreatedAt(new \DateTimeImmutable());

        $em->persist($msg);
        $em->flush();

        return $this->json(['success' => true], 201);
    }

    #[Route('/api/offers', methods: ['POST'])]
    public function createOffer(
        Request $request,
        EntityManagerInterface $em
    ): JsonResponse {

        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        $data = json_decode($request->getContent(), true);

        if (!isset($data['professional_id'], $data['price'])) {
            return $this->json(['error' => 'Invalid payload'], 400);
        }

        $professional = $em->getRepository(Professional::class)
            ->find($data['professional_id']);

        if (!$professional) {
            return $this->json(['error' => 'Professional not found'], 404);
        }

        $offer = new Offer();
        $offer->setClient($user);
        $offer->setProfessional($professional);
        $offer->setPrice((float) $data['price']);
        $offer->setMessage($data['message'] ?? null);

        $em->persist($offer);
        $em->flush();

        return $this->json(['success' => true, 'status' => 'pending']);
    }
}
