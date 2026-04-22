<?php
namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Evento;
use App\Enum\EstadoEventoEnum;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class EventoCancelProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private readonly ProcessorInterface $persistProcessor,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if (!$data instanceof Evento) {
            return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
        }

        $data->setEstado(EstadoEventoEnum::CANCELADO);

        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }
}

