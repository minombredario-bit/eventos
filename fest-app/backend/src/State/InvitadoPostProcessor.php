<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Invitado;
use App\Entity\RelacionUsuario;
use App\Entity\Usuario;
use App\Repository\InvitadoRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class InvitadoPostProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly InvitadoRepository $invitadoRepository,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if (!$data instanceof Invitado) {
            return $data;
        }

        // Historically this check was used to prevent guest management when the
        // event explicitly disallowed it. In most flows the caller ensures the
        // endpoint is correct; keep processor focused on duplicate detection.

        $creador = $data->getCreadoPor();
        $normalizedInvitadoName = InvitadoRepository::normalizeName($data->getNombre(), $data->getApellidos());

        if ($this->matchesHouseholdMemberName($creador, $normalizedInvitadoName)) {
            throw new UnprocessableEntityHttpException('Ya existe una persona del núcleo familiar con ese nombre completo.');
        }

        if ($this->invitadoRepository->existsActiveByEventoAndHouseholdAndNormalizedName(
            $data->getEvento(),
            $creador,
            $normalizedInvitadoName,
        )) {
            throw new UnprocessableEntityHttpException('Ya existe un invitado activo con ese nombre completo en tu núcleo familiar para este evento.');
        }

        $invitadoBorrado = $this->invitadoRepository->findDeletedByEventoAndHouseholdAndNormalizedName(
            $data->getEvento(),
            $creador,
            $normalizedInvitadoName,
        );

        if ($invitadoBorrado instanceof Invitado) {
            $invitadoBorrado
                ->setDeletedAt(null)
                ->setNombre($data->getNombre())
                ->setApellidos($data->getApellidos())
                ->setTipoPersona($data->getTipoPersona())
                ->setObservaciones($data->getObservaciones());

            $this->entityManager->flush();

            return $invitadoBorrado;
        }

        $this->entityManager->persist($data);
        $this->entityManager->flush();

        return $data;
    }

    private function matchesHouseholdMemberName(Usuario $creador, string $normalizedInvitadoName): bool
    {
        if ($normalizedInvitadoName === '') {
            return false;
        }

        if (InvitadoRepository::normalizeName($creador->getNombre(), $creador->getApellidos()) === $normalizedInvitadoName) {
            return true;
        }

        foreach ($creador->getRelacionados() as $relacion) {
            if (!$relacion instanceof RelacionUsuario) {
                continue;
            }

            $relacionado = (string) $relacion->getUsuarioOrigen()->getId() === (string) $creador->getId()
                ? $relacion->getUsuarioDestino()
                : $relacion->getUsuarioOrigen();

            if (InvitadoRepository::normalizeName($relacionado->getNombre(), $relacionado->getApellidos()) === $normalizedInvitadoName) {
                return true;
            }
        }

        return false;
    }
}
