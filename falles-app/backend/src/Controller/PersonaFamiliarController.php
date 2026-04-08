<?php

namespace App\Controller;

use App\Entity\RelacionUsuario;
use App\Entity\Usuario;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/persona_familiares')]
class PersonaFamiliarController extends AbstractController
{
    #[Route('/mias', name: 'api_persona_familiares_mias', methods: ['GET'])]
    public function mias(): JsonResponse
    {
        /** @var Usuario $user */
        $user = $this->getUser();

        $items = [];
        $seen = [];

        foreach ($user->getRelacionados() as $relacion) {
            if (!$relacion instanceof RelacionUsuario) {
                continue;
            }

            $destino = $relacion->getUsuarioOrigen()->getId() === $user->getId()
                ? $relacion->getUsuarioDestino()
                : $relacion->getUsuarioOrigen();

            $destinoId = (string) $destino->getId();
            if ($destinoId === '' || isset($seen[$destinoId])) {
                continue;
            }

            $seen[$destinoId] = true;
            $items[] = [
                'id' => $destinoId,
                'nombre' => $destino->getNombre(),
                'apellidos' => $destino->getApellidos(),
                'nombreCompleto' => $destino->getNombreCompleto(),
                'parentesco' => $relacion->getTipoRelacion()->value,
                'tipoPersona' => 'adulto',
                'observaciones' => null,
                'inscripcion' => null,
                '@id' => '/api/usuarios/' . $destinoId,
            ];
        }

        return $this->json([
            'hydra:member' => $items,
            'hydra:totalItems' => count($items),
        ]);
    }
}
