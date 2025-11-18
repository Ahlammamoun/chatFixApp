<?php

namespace App\Controller;

use App\Entity\Speciality;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class SpecialityController extends AbstractController
{
    #[Route('/api/specialities', name: 'get_specialities', methods: ['GET'])]
    public function list(EntityManagerInterface $em): JsonResponse
    {
        $specialities = $em->getRepository(Speciality::class)->findBy([], ['name' => 'ASC']);

        $data = array_map(fn(Speciality $s) => [
            'id' => $s->getId(),
            'name' => $s->getName(),
        ], $specialities);

        return $this->json($data);
    }
}
