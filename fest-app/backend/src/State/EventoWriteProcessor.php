<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Entidad;
use App\Entity\Evento;
use App\Entity\Usuario;
use App\Service\EmailQueueService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\String\Slugger\AsciiSlugger;

class EventoWriteProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private readonly ProcessorInterface $persistProcessor,
        private readonly EmailQueueService $emailQueueService,
        private readonly EntityManagerInterface $entityManager,
        private readonly Security $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if (!$data instanceof Evento) {
            return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
        }

        $admin = $this->security->getUser();
        if (!$admin instanceof Usuario) {
            throw new AccessDeniedHttpException('Usuario no autenticado.');
        }

        $entidad = $admin->getEntidad();
        if (!$entidad instanceof Entidad) {
            throw new AccessDeniedHttpException('El administrador no tiene entidad asignada.');
        }
        foreach ($context['data']->getActividades() as $key => $actividad) {
            $actividad->setNombre('test' . $key);
        }
        $isCreate = !isset($context['previous_data']) || !$context['previous_data'] instanceof Evento;

        $data->setEntidad($entidad);
        if ($isCreate) {
            $data->setSlug($this->generateUniqueSlug($data->getTitulo(), $entidad));
        }

        $result = $this->persistProcessor->process($data, $operation, $uriVariables, $context);

        if ($result instanceof Evento) {
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

    private function generateUniqueSlug(string $titulo, Entidad $entidad): string
    {
        $slugger = new AsciiSlugger('es');
        $base = strtolower($slugger->slug($titulo)->toString());

        if ($base === '') {
            $base = 'evento';
        }

        $candidate = $base;
        $suffix = 2;

        while ($this->slugExists($candidate, $entidad)) {
            $candidate = sprintf('%s-%d', $base, $suffix);
            $suffix++;
        }

        return $candidate;
    }

    private function slugExists(string $slug, Entidad $entidad): bool
    {
        return null !== $this->entityManager->getRepository(Evento::class)->findOneBy([
            'entidad' => $entidad,
            'slug' => $slug,
        ]);
    }
}

