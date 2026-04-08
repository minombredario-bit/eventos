<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Dto\PersonaFamiliarView;
use App\Entity\RelacionUsuario;
use App\Entity\Usuario;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class PersonaFamiliarMiasProvider implements ProviderInterface
{
    public function __construct(
        private readonly Security $security,
    ) {
    }

    /**
     * @return list<PersonaFamiliarView>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $user = $this->security->getUser();

        if (!$user instanceof Usuario) {
            throw new AccessDeniedHttpException('No autenticado.');
        }

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

            $item = new PersonaFamiliarView();
            $item->id = $destinoId;
            $item->nombre = $destino->getNombre();
            $item->apellidos = $destino->getApellidos();
            $item->nombreCompleto = $destino->getNombreCompleto();
            $item->parentesco = $relacion->getTipoRelacion()->value;
            $item->iri = '/api/usuarios/' . $destinoId;

            $items[] = $item;
        }

        return $items;
    }
}
