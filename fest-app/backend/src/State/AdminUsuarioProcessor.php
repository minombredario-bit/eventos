<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\AdminCreateUsuarioInput;
use App\Dto\AdminUpdateUsuarioInput;
use App\Entity\Usuario;
use App\Enum\CensadoViaEnum;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class AdminUsuarioProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Security $security,
    ) {}

    public function process(
        mixed $data,
        Operation $operation,
        array $uriVariables = [],
        array $context = []
    ): Usuario {
        $admin = $this->getAdmin();

        return match (true) {
            $data instanceof AdminCreateUsuarioInput =>
            $this->handleCreate($data, $admin),

            $data instanceof AdminUpdateUsuarioInput =>
            $this->handleUpdate($data, $admin, $uriVariables),

            default =>
            throw new \InvalidArgumentException('Payload no soportado'),
        };
    }

    private function handleCreate(
        AdminCreateUsuarioInput $data,
        Usuario $admin
    ): Usuario {
        $entidad = $admin->getEntidad();

        if (!$entidad) {
            throw new BadRequestHttpException('El admin no tiene entidad');
        }

        $usuario = new Usuario();

        $usuario
            ->setEntidad($entidad)
            ->setCensadoVia(CensadoViaEnum::MANUAL)
            ->setFechaAltaCenso(new \DateTimeImmutable());

        $this->applyCommon($usuario, $data, true);

        $this->entityManager->persist($usuario);
        $this->entityManager->flush();

        return $usuario;
    }

    private function handleUpdate(
        AdminUpdateUsuarioInput $data,
        Usuario $admin,
        array $uriVariables
    ): Usuario {
        $id = $uriVariables['id'] ?? null;

        if (!$id) {
            throw new BadRequestHttpException('ID requerido');
        }

        $usuario = $this->entityManager->find(Usuario::class, $id);

        if (!$usuario) {
            throw new BadRequestHttpException('Usuario no encontrado');
        }

        $this->assertCanEdit($admin, $usuario);

        $this->applyCommon($usuario, $data, false);

        $this->entityManager->flush();

        return $usuario;
    }

    private function applyCommon(
        Usuario $usuario,
        AdminCreateUsuarioInput|AdminUpdateUsuarioInput $data,
        bool $isCreate
    ): void {
        if ($isCreate || $data->nombre !== null) {
            $usuario->setNombre(trim($data->nombre));
        }

        if ($isCreate || $data->apellidos !== null) {
            $usuario->setApellidos(trim($data->apellidos));
        }

        if ($isCreate || $data->email !== null) {
            $usuario->setEmail(
                $data->email ? mb_strtolower(trim($data->email)) : null
            );
        }

        if ($isCreate || $data->telefono !== null) {
            $usuario->setTelefono($this->normalizeNullable($data->telefono));
        }

        if ($isCreate || $data->activo !== null) {
            $usuario->setActivo((bool) $data->activo);
        }
    }

    private function normalizeNullable(?string $value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    private function getAdmin(): Usuario
    {
        $user = $this->security->getUser();

        if (!$user instanceof Usuario) {
            throw new AccessDeniedHttpException('No autenticado');
        }

        if (
            !in_array('ROLE_ADMIN', $user->getRoles(), true) &&
            !in_array('ROLE_ADMIN_ENTIDAD', $user->getRoles(), true)
        ) {
            throw new AccessDeniedHttpException('Sin permisos');
        }

        return $user;
    }

    private function assertCanEdit(Usuario $admin, Usuario $usuario): void
    {
        if ($admin->getEntidad()?->getId() !== $usuario->getEntidad()?->getId()) {
            throw new AccessDeniedHttpException('No puedes editar este usuario');
        }
    }
}
