<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Evento;
use App\Service\EmailQueueService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class EventoWriteProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private readonly ProcessorInterface $persistProcessor,
        private readonly EmailQueueService $emailQueueService,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        $result = $this->persistProcessor->process($data, $operation, $uriVariables, $context);

        if ($result instanceof Evento) {
            $isCreate = !isset($context['previous_data']) || !$context['previous_data'] instanceof Evento;
            if ($isCreate) {
                $this->emailQueueService->enqueueEventoCreado($result);
            } else {
                // Para cambios de evento reutilizamos la misma plantilla de anuncio.
                $this->emailQueueService->enqueueEventoCreado($result);
            }
            $this->entityManager->flush();
        }

        return $result;
    }
}

