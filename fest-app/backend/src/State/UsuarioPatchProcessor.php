<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Usuario;
use App\Service\EmailQueueService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class UsuarioPatchProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Security $security,
        private readonly EmailQueueService $emailQueueService,
        private readonly string $appUri,
    ) {}

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Usuario
    {
        if (!$data instanceof Usuario) {
            throw new \InvalidArgumentException('Solo se esperan objetos Usuario');
        }

        // Obtener el usuario guardado en la base de datos antes de hacer cambios
        $usuarioGuardado = $this->entityManager->find(Usuario::class, $uriVariables['id'] ?? null);

        if (!$usuarioGuardado instanceof Usuario) {
            throw new \InvalidArgumentException('Usuario no encontrado');
        }

        // Verificar que el usuario actual pueda editar
        $currentUser = $this->security->getUser();
        if (!$currentUser instanceof Usuario || !$this->canEdit($currentUser, $usuarioGuardado)) {
            throw new AccessDeniedHttpException('No tienes permiso para editar este usuario');
        }

        // Guardar email anterior para detectar cambios
        $emailAnterior = $usuarioGuardado->getEmail();

        // Actualizar los datos
        $this->entityManager->persist($data);
        $this->entityManager->flush();

        // Notificar si el email cambió
        if ($emailAnterior !== $data->getEmail() && $emailAnterior && $data->getEmail()) {
            $this->emailQueueService->enqueueUserEmailChanged(
                $data,
                $emailAnterior,
                $data->getEmail(),
                $this->appUri
            );
            $this->entityManager->flush();
        }

        return $data;
    }

    private function canEdit(Usuario $currentUser, Usuario $usuarioAEditar): bool
    {
        // Un usuario puede editar su propio perfil
        if ($currentUser->getId() === $usuarioAEditar->getId()) {
            return true;
        }

        // Un admin de entidad puede editar usuarios de su entidad
        if ($currentUser->getEntidad()->getId() === $usuarioAEditar->getEntidad()->getId()) {
            return in_array('ROLE_ADMIN_ENTIDAD', $currentUser->getRoles(), true);
        }

        return false;
    }
}

