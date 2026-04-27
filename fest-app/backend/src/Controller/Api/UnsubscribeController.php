<?php

namespace App\Controller\Api;

use App\Entity\Usuario;
use App\Repository\RelacionUsuarioRepository;
use App\Service\EmailQueueService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

#[Route('/api')]
class UnsubscribeController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly RelacionUsuarioRepository $relacionUsuarioRepository,
        private readonly EmailQueueService $emailQueueService,
    ) {}

    /**
     * Recibe una lista de usuarios para "dar de baja" o "quitar del grupo familiar".
     * Payload esperado (JSON): { users: ["uuid", ...], action: "baja"|"quitar", reason: "texto opcional" }
     */
    #[Route('/me/solicitar-baja', name: 'api_me_solicitar_baja', methods: ['POST'])]
    public function solicitarBaja(Request $request, #[CurrentUser] ?Usuario $user): JsonResponse
    {
        if (null === $user) {
            return new JsonResponse(['message' => 'No autenticado'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        $payload = $request->toArray();

        $ids = $payload['memberIds'] ?? null;
        $action = isset($payload['action']) ? (string) $payload['action'] : 'baja';
        $reason = isset($payload['reason']) ? (string) $payload['reason'] : null;

        if (!is_array($ids)) {
            throw new BadRequestHttpException('El campo users debe ser un array de ids.');
        }

        if (!in_array($action, ['baja', 'quitar'], true)) {
            throw new BadRequestHttpException('Action inválida. Debe ser "baja" o "quitar".');
        }

        $results = [
            'processed' => [],
            'failed' => [],
        ];

        foreach ($ids as $targetId) {
            if (!is_string($targetId) || trim($targetId) === '') {
                $results['failed'][] = ['id' => $targetId, 'reason' => 'id inválido'];
                continue;
            }

            // Verificar que el usuario objetivo esté relacionado con el usuario autenticado
            $relacionado = $this->relacionUsuarioRepository->findRelacionadoByUsuarioYRelacionadoId($user, $targetId);

            if ($relacionado === null) {
                $results['failed'][] = ['id' => $targetId, 'reason' => 'No pertenece al grupo familiar del usuario autenticado.'];
                continue;
            }

            try {
                if ($action === 'baja') {
                    // marcar como no activo y guardar motivo si existe
                    $relacionado->setActivo(false);
                    if ($reason !== null && $reason !== '') {
                        $relacionado->setMotivoBajaCenso($reason);
                    }
                    $this->entityManager->persist($relacionado);
                    $results['processed'][] = ['id' => $targetId, 'action' => 'baja'];
                } else {
                    // quitar del grupo familiar: buscar relaciones y eliminarlas
                    $found = false;
                    foreach ($user->getRelacionados() as $rel) {
                        $otro = $rel->getUsuarioOrigen()->getId() === $user->getId()
                            ? $rel->getUsuarioDestino()
                            : $rel->getUsuarioOrigen();

                        if ($otro->getId() === $targetId) {
                            $this->entityManager->remove($rel);
                            $found = true;
                        }
                    }

                    if (!$found) {
                        // por si acaso, intentar encontrar relación inversa buscando en la base
                        $rel = $this->entityManager->getRepository(\App\Entity\RelacionUsuario::class)
                            ->createQueryBuilder('r')
                            ->where('(IDENTITY(r.usuarioOrigen) = :u1 AND IDENTITY(r.usuarioDestino) = :u2) OR (IDENTITY(r.usuarioOrigen) = :u2 AND IDENTITY(r.usuarioDestino) = :u1)')
                            ->setParameter('u1', $user->getId())
                            ->setParameter('u2', $targetId)
                            ->setMaxResults(1)
                            ->getQuery()
                            ->getOneOrNullResult();

                        if ($rel instanceof \App\Entity\RelacionUsuario) {
                            $this->entityManager->remove($rel);
                            $found = true;
                        }
                    }

                    if ($found) {
                        $results['processed'][] = ['id' => $targetId, 'action' => 'quitar'];
                    } else {
                        $results['failed'][] = ['id' => $targetId, 'reason' => 'Relación no encontrada para eliminar.'];
                    }
                }
            } catch (\Throwable $e) {
                $results['failed'][] = ['id' => $targetId, 'reason' => $e->getMessage()];
            }
        }

        $this->entityManager->flush();

        // Encolar correo informando al equipo/administradores de la entidad
        $recipients = [];
        foreach ($user->getEntidad()->getUsuarios() as $u) {
            if (!$u->isActivo()) {
                continue;
            }

            $roles = $u->getRoles();
            if (in_array('ROLE_ADMIN_ENTIDAD', $roles, true) || in_array('ROLE_ADMIN', $roles, true) || in_array('ROLE_SUPERADMIN', $roles, true)) {
                $recipients[] = $u->getEmail();
            }
        }

        $recipients = array_values(array_unique($recipients));

        if ($recipients !== []) {
            $context = [
                'requestor' => $user->getNombreCompleto(),
                'requestor_email' => $user->getEmail(),
                'action' => $action,
                'reason' => $reason,
                'targets' => $results['processed'],
            ];

            foreach ($recipients as $email) {
                $this->emailQueueService->enqueue(
                    $email,
                    'Solicitud de baja / cambio en grupo familiar',
                    'email/solicitar_baja.html.twig',
                    $context,
                    $user->getEntidad(),
                    $user
                );
            }
        }

        return new JsonResponse([
            'ok' => true,
            'results' => $results,
            'emails_sent_to' => $recipients,
        ]);
    }

}

