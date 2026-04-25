<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\RelacionUsuario;
use App\Entity\Usuario;
use App\Repository\RelacionUsuarioRepository;
use App\Enum\TipoRelacionEnum;
use App\Repository\UsuarioRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class RelacionUsuarioProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UsuarioRepository $usuarioRepository,
        private readonly RelacionUsuarioRepository $relacionUsuarioRepository,
        private readonly Security $security,
    ) {}

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): RelacionUsuario
    {
        if (!$data instanceof RelacionUsuario) {
            throw new BadRequestHttpException('Payload de relacion no valido.');
        }

        $relacion = $data;

        // El usuario origen viene de la URL
        $usuarioOrigenId = $uriVariables['id'] ?? null;
        $usuarioOrigen = $this->usuarioRepository->find($usuarioOrigenId);

        if (!$usuarioOrigen) {
            throw new NotFoundHttpException('Usuario origen no encontrado.');
        }

        try {
            $usuarioDestino = $relacion->getUsuarioDestino();
        } catch (\Error) {
            $usuarioDestino = null;
        }
        if (!$usuarioDestino) {
            throw new BadRequestHttpException('Usuario destino no encontrado.');
        }

        $usuarioActual = $this->security->getUser();
        if (!$usuarioActual instanceof Usuario) {
            throw new AccessDeniedHttpException('No autenticado.');
        }

        $isOwner = $usuarioActual->getId() === $usuarioOrigen->getId();
        $isSuperadmin = $this->security->isGranted('ROLE_SUPERADMIN');
        $isAdminEntidad = $this->security->isGranted('ROLE_ADMIN_ENTIDAD')
            && $usuarioActual->getEntidad()->getId() === $usuarioOrigen->getEntidad()->getId();

        if (!$isOwner && !$isAdminEntidad && !$isSuperadmin) {
            throw new AccessDeniedHttpException('No tienes permiso para crear relaciones para este usuario.');
        }

        if ($usuarioOrigen->getEntidad()->getId() !== $usuarioDestino->getEntidad()->getId() && !$isSuperadmin) {
            throw new AccessDeniedHttpException('Solo se permiten relaciones dentro de la misma entidad.');
        }

        if ($usuarioOrigen->getId() === $usuarioDestino->getId()) {
            throw new BadRequestHttpException('No se puede crear una relacion con el mismo usuario.');
        }

        $existingRelation = $this->relacionUsuarioRepository->createQueryBuilder('r')
            ->andWhere('r.tipoRelacion = :tipoRelacion')
            ->andWhere('(
                (r.usuarioOrigen = :usuarioOrigen AND r.usuarioDestino = :usuarioDestino)
                OR
                (r.usuarioOrigen = :usuarioDestino AND r.usuarioDestino = :usuarioOrigen)
            )')
            ->setParameter('tipoRelacion', $relacion->getTipoRelacion())
            ->setParameter('usuarioOrigen', $usuarioOrigen)
            ->setParameter('usuarioDestino', $usuarioDestino)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if ($existingRelation instanceof RelacionUsuario) {
            throw new ConflictHttpException('La relacion ya existe.');
        }

        $relacion->setUsuarioOrigen($usuarioOrigen);
        $relacion->setUsuarioDestino($usuarioDestino);

        $this->entityManager->persist($relacion);

        // Intentar crear la relación inversa automáticamente si existe un tipo inverso definido.
        $inverseType = $this->getInverseTipo($relacion->getTipoRelacion());
        if ($inverseType !== null) {
            // Comprobar si ya existe una relación del tipo inverso entre los mismos usuarios
            $existingInverse = $this->relacionUsuarioRepository->createQueryBuilder('r')
                ->andWhere('r.tipoRelacion = :tipoRelacion')
                ->andWhere('(
                    (r.usuarioOrigen = :usuarioDestino AND r.usuarioDestino = :usuarioOrigen)
                    OR
                    (r.usuarioOrigen = :usuarioOrigen AND r.usuarioDestino = :usuarioDestino)
                )')
                ->setParameter('tipoRelacion', $inverseType)
                ->setParameter('usuarioOrigen', $usuarioOrigen)
                ->setParameter('usuarioDestino', $usuarioDestino)
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();

            if (!$existingInverse instanceof RelacionUsuario) {
                $inverse = new RelacionUsuario();
                $inverse->setUsuarioOrigen($usuarioDestino);
                $inverse->setUsuarioDestino($usuarioOrigen);
                $inverse->setTipoRelacion($inverseType);
                $this->entityManager->persist($inverse);
            }
        }

        $this->entityManager->flush();

        return $relacion;
    }

    private function getInverseTipo(TipoRelacionEnum $tipo): ?TipoRelacionEnum
    {
        return match ($tipo) {
            TipoRelacionEnum::PADRE => TipoRelacionEnum::HIJO,
            TipoRelacionEnum::MADRE => TipoRelacionEnum::HIJA,
            TipoRelacionEnum::HIJO => TipoRelacionEnum::PADRE,
            TipoRelacionEnum::HIJA => TipoRelacionEnum::MADRE,
            TipoRelacionEnum::SOBRINO => TipoRelacionEnum::TIO,
            TipoRelacionEnum::SOBRINA => TipoRelacionEnum::TIA,
            TipoRelacionEnum::TIO => TipoRelacionEnum::SOBRINO,
            TipoRelacionEnum::TIA => TipoRelacionEnum::SOBRINA,
            TipoRelacionEnum::ABUELO => TipoRelacionEnum::ABUELA, // fallback: make them symmetric-ish
            TipoRelacionEnum::ABUELA => TipoRelacionEnum::ABUELO,
            default => null,
        };
    }
}
