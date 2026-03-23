<?php

namespace App\Controller;

use App\Entity\PersonaFamiliar;
use App\Entity\Usuario;
use App\Repository\PersonaFamiliarRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api')]
class PersonaFamiliarController extends AbstractController
{
    public function __construct(
        private readonly PersonaFamiliarRepository $personaFamiliarRepository,
    ) {}

    /**
     * List active family members for current user.
     */
    #[Route('/persona_familiares/mias', name: 'api_persona_familiares_mias', methods: ['GET'])]
    public function mias(): JsonResponse
    {
        /** @var Usuario $user */
        $user = $this->getUser();
        $personas = $this->personaFamiliarRepository->findActivasByUsuario($user);

        return $this->json([
            'hydra:member' => array_map(fn(PersonaFamiliar $persona) => [
                'id' => $persona->getId(),
                'nombre' => $persona->getNombre(),
                'apellidos' => $persona->getApellidos(),
                'nombreCompleto' => $persona->getNombreCompleto(),
                'parentesco' => $persona->getParentesco(),
                'tipoPersona' => $persona->getTipoPersona()->value,
                'observaciones' => $persona->getObservaciones(),
            ], $personas),
        ]);
    }
}
