<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Usuario;
use App\Service\CensoImporterService;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

final class ExportarUsuariosExcelProvider implements ProviderInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.collection_provider')]
        private readonly ProviderInterface $collectionProvider,
        private readonly CensoImporterService $censoImporterService,
        private readonly RequestStack $requestStack,
    ) {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): BinaryFileResponse
    {
        /** @var iterable<Usuario> $coleccion */
        $coleccion = $this->collectionProvider->provide($operation, $uriVariables, $context);

        $usuarios = $coleccion instanceof \Traversable
            ? iterator_to_array($coleccion, false)
            : (array) $coleccion;

        $request = $this->requestStack->getCurrentRequest();

        $soloCumples = $request?->query->getBoolean('soloCumples', false) ?? false;

        $filePath = $soloCumples
            ? $this->censoImporterService->exportarCumples($usuarios)
            : $this->censoImporterService->exportar($usuarios);

        $response = new BinaryFileResponse($filePath);

        $response->headers->set(
            'Content-Type',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );

        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            basename($filePath)
        );

        $response->deleteFileAfterSend(true);

        return $response;
    }
}
