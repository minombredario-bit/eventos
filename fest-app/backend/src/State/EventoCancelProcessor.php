<?php
namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Evento;
use App\Enum\EstadoEventoEnum;
use App\Service\EventoPushNotifier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class EventoCancelProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private readonly ProcessorInterface $persistProcessor,
        private readonly EventoPushNotifier $eventoPushNotifier,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if (!$data instanceof Evento) {
            return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
        }

        $data->setEstado(EstadoEventoEnum::CANCELADO);

        /** @var Evento $saved */
        $saved = $this->persistProcessor->process($data, $operation, $uriVariables, $context);

        // Notificar que el evento ha sido cancelado
        $this->eventoPushNotifier->notifyEventoCancelado($saved);

        return $saved;
    }
}

