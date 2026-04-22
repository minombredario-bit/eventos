<?php
namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Evento;
use App\Repository\InscripcionRepository;
use App\Repository\PagoRepository;
use App\Entity\Audit;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class EventoForceDeleteProcessor implements ProcessorInterface
{
    public function __construct(
        #[\Symfony\Component\DependencyInjection\Attribute\Autowire(service: 'api_platform.doctrine.orm.state.remove_processor')]
        private readonly ProcessorInterface $removeProcessor,
        private readonly InscripcionRepository $inscripcionRepository,
        private readonly PagoRepository $pagoRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly Security $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if (!$data instanceof Evento) {
            return $this->removeProcessor->process($data, $operation, $uriVariables, $context);
        }

        $user = $this->security->getUser();
        if (!$this->security->isGranted('ROLE_SUPERADMIN')) {
            throw new AccessDeniedHttpException('Necesitas ROLE_SUPERADMIN para forzar el borrado.');
        }

        // Auditoría: registrar intención de borrado de forma genérica
        $audit = new Audit();
        $entityType = (new \ReflectionClass($data))->getShortName();
        $audit->setEntityType($entityType);
        $audit->setEntityId((string) $data->getId());
        $user = $this->security->getUser();
        $audit->setActorId($user && method_exists($user, 'getId') ? (string) $user->getId() : null);
        $audit->setCreatedAt(new \DateTimeImmutable());
        // store a small payload snapshot (id and class) to help future debugging
        $audit->setAction('delete');
        $audit->setChanges(['snapshot' => ['id' => $data->getId(), 'class' => $entityType], 'deleted' => ['pagos' => [], 'inscripciones' => []]]);
        $this->entityManager->persist($audit);

        // Eliminar pagos e inscripciones asociados antes de borrar el evento.
        $inscripciones = $this->inscripcionRepository->findBy(['evento' => $data]);

        foreach ($inscripciones as $inscripcion) {
            $pagos = $this->pagoRepository->findBy(['inscripcion' => $inscripcion]);
            foreach ($pagos as $pago) {
                $this->entityManager->remove($pago);
                // record removed pago id if possible
                if (method_exists($pago, 'getId')) {
                    $auditChanges = $audit->getChanges();
                    $auditChanges['deleted']['pagos'][] = (string) $pago->getId();
                    $audit->setChanges($auditChanges);
                }
            }

            if (method_exists($inscripcion, 'getId')) {
                $auditChanges = $audit->getChanges();
                $auditChanges['deleted']['inscripciones'][] = (string) $inscripcion->getId();
                $audit->setChanges($auditChanges);
            }

            $this->entityManager->remove($inscripcion);
        }

        // Delegate to the standard remove processor (will call remove + flush)
        return $this->removeProcessor->process($data, $operation, $uriVariables, $context);
    }
}

