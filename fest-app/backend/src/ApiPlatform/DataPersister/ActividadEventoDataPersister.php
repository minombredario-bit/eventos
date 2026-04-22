<?php
namespace App\ApiPlatform\DataPersister;

use ApiPlatform\State\ProcessorInterface;
use ApiPlatform\Metadata\Operation;
use App\Entity\ActividadEvento;
use App\Repository\InscripcionLineaRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class ActividadEventoDataPersister implements ProcessorInterface
{
    public function __construct(private readonly EntityManagerInterface $em, private readonly InscripcionLineaRepository $inscripcionLineaRepository)
    {
    }

    /**
     * Process the resource state. We only handle DELETE here.
     *
     * @param mixed $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        // Only act on ActividadEvento instances
        if (!$data instanceof ActividadEvento) {
            return $data;
        }

        // If operation method is DELETE, perform check and remove
        $method = $operation->getMethod();
        if (null !== $method && strtoupper($method) === 'DELETE') {
            $count = $this->inscripcionLineaRepository->count(['actividad' => $data]);
            if ($count > 0) {
                throw new ConflictHttpException('No se puede eliminar la actividad: existen inscripciones.');
            }

            $this->em->remove($data);
            $this->em->flush();

            return null;
        }

        // For other methods, default behaviour: persist and flush
        $this->em->persist($data);
        $this->em->flush();

        return $data;
    }
}

