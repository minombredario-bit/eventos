<?php

namespace App\Controller\Superadmin;

use App\Entity\Entidad;
use App\Entity\Usuario;
use App\Repository\EntidadRepository;
use App\Repository\CensoEntradaRepository;
use App\Service\CensoImporterService;
use App\Service\CodeGeneratorService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/superadmin')]
class SuperadminController extends AbstractController
{
    public function __construct(
        private readonly EntidadRepository $entidadRepository,
        private readonly CensoEntradaRepository $censoRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly CensoImporterService $censoImporter,
        private readonly CodeGeneratorService $codeGenerator
    ) {}

    /**
     * List all entities.
     */
    #[Route('/entidades', name: 'api_superadmin_entidades_list', methods: ['GET'])]
    public function listEntidades(): JsonResponse
    {
        $entidades = $this->entidadRepository->findAll();

        $data = array_map(fn(Entidad $entidad) => [
            'id' => $entidad->getId(),
            'nombre' => $entidad->getNombre(),
            'slug' => $entidad->getSlug(),
            'tipoEntidad' => $entidad->getTipoEntidad()->value,
            'emailContacto' => $entidad->getEmailContacto(),
            'activa' => $entidad->isActiva(),
            'temporadaActual' => $entidad->getTemporadaActual(),
            'createdAt' => $entidad->getCreatedAt()->format('c'),
        ], $entidades);

        return $this->json(['hydra:member' => $data]);
    }

    /**
     * Create a new entity.
     */
    #[Route('/entidades', name: 'api_superadmin_entidades_create', methods: ['POST'])]
    public function createEntidad(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $required = ['nombre', 'slug', 'tipoEntidad', 'emailContacto', 'temporadaActual'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return $this->json(['error' => "Campo requerido: {$field}"], 400);
            }
        }

        // Check slug uniqueness
        if ($this->entidadRepository->findOneBySlug($data['slug'])) {
            return $this->json(['error' => 'El slug ya existe'], 409);
        }

        $entidad = new Entidad();
        $entidad->setNombre($data['nombre']);
        $entidad->setSlug($data['slug']);
        $entidad->setTipoEntidad(\App\Enum\TipoEntidadEnum::from($data['tipoEntidad']));
        $entidad->setEmailContacto($data['emailContacto']);
        $entidad->setTemporadaActual($data['temporadaActual']);
        $entidad->setDescripcion($data['descripcion'] ?? null);
        $entidad->setTelefono($data['telefono'] ?? null);
        $entidad->setDireccion($data['direccion'] ?? null);
        $entidad->setTerminologiaSocio($data['terminologiaSocio'] ?? null);
        $entidad->setTerminologiaEvento($data['terminologiaEvento'] ?? null);
        $entidad->setActiva($data['activa'] ?? true);
        
        // Generate registration code
        $entidad->setCodigoRegistro($this->codeGenerator->generateRegistroCode());

        $this->entityManager->persist($entidad);
        $this->entityManager->flush();

        return $this->json([
            'id' => $entidad->getId(),
            'nombre' => $entidad->getNombre(),
            'slug' => $entidad->getSlug(),
            'codigoRegistro' => $entidad->getCodigoRegistro(),
            'tipoEntidad' => $entidad->getTipoEntidad()->value,
        ], 201);
    }

    /**
     * Regenerate entity registration code.
     */
    #[Route('/entidades/{id}/codigo-registro/regenerar', name: 'api_superadmin_entidad_regenerar_codigo', methods: ['POST'])]
    public function regenerarCodigo(int $id): JsonResponse
    {
        $entidad = $this->entidadRepository->find($id);

        if (!$entidad) {
            return $this->json(['error' => 'Entidad no encontrada'], 404);
        }

        $codigoAnterior = $entidad->getCodigoRegistro();
        $nuevoCodigo = $this->codeGenerator->generateRegistroCode();
        
        $entidad->setCodigoRegistro($nuevoCodigo);
        $this->entityManager->flush();

        return $this->json([
            'id' => $entidad->getId(),
            'codigoAnterior' => $codigoAnterior,
            'codigoNuevo' => $nuevoCodigo,
            'mensaje' => 'Código regenerado. El código anterior ya no es válido.',
        ]);
    }

    /**
     * Import census from Excel.
     */
    #[Route('/entidades/{id}/censo/importar', name: 'api_superadmin_entidad_importar_censo', methods: ['POST'])]
    public function importarCenso(int $id, Request $request): JsonResponse
    {
        $entidad = $this->entidadRepository->find($id);

        if (!$entidad) {
            return $this->json(['error' => 'Entidad no encontrada'], 404);
        }

        /** @var UploadedFile $file */
        $file = $request->files->get('file');

        if (!$file) {
            return $this->json(['error' => 'Archivo no proporcionado'], 400);
        }

        // Validate file type
        $extension = strtolower($file->getClientOriginalExtension());
        if (!in_array($extension, ['xlsx', 'xls'])) {
            return $this->json(['error' => 'Formato de archivo no válido. Use .xlsx o .xls'], 400);
        }

        // Get temporada from request or use entity's current season
        $temporada = $request->request->get('temporada') ?? $entidad->getTemporadaActual();

        // Save file temporarily
        $tempPath = sys_get_temp_dir() . '/' . uniqid('censo_') . '.' . $extension;
        $file->move(dirname($tempPath), basename($tempPath));

        try {
            $resultado = $this->censoImporter->importar($tempPath, $entidad, $temporada);
            
            // Clean up temp file
            unlink($tempPath);

            return $this->json([
                'total' => $resultado['total'],
                'insertadas' => $resultado['insertadas'],
                'actualizadas' => $resultado['actualizadas'] ?? 0,
                'errores' => $resultado['errores'],
            ]);
        } catch (\Exception $e) {
            // Clean up temp file on error
            if (file_exists($tempPath)) {
                unlink($tempPath);
            }
            return $this->json(['error' => 'Error procesando el archivo: ' . $e->getMessage()], 500);
        }
    }

    /**
     * View entity census.
     */
    #[Route('/entidades/{id}/censo', name: 'api_superadmin_entidad_censo', methods: ['GET'])]
    public function verCenso(int $id): JsonResponse
    {
        $entidad = $this->entidadRepository->find($id);

        if (!$entidad) {
            return $this->json(['error' => 'Entidad no encontrada'], 404);
        }

        $entradas = $this->censoRepository->findBy(['entidad' => $entidad], ['apellidos' => 'ASC']);

        $data = array_map(fn(CensoEntrada $entrada) => [
            'id' => $entrada->getId(),
            'nombre' => $entrada->getNombre(),
            'apellidos' => $entrada->getApellidos(),
            'email' => $entrada->getEmail(),
            'dni' => $entrada->getDni(),
            'parentesco' => $entrada->getParentesco(),
            'tipoPersona' => $entrada->getTipoPersona()->value,
            'tipoRelacionEconomica' => $entrada->getTipoRelacionEconomica()->value,
            'temporada' => $entrada->getTemporada(),
            'procesado' => $entrada->isProcesado(),
            'usuarioVinculado' => $entrada->getUsuarioVinculado()?->getEmail(),
        ], $entradas);

        return $this->json(['hydra:member' => $data]);
    }

    /**
     * View unlinked census entries.
     */
    #[Route('/entidades/{id}/censo/sin-vincular', name: 'api_superadmin_entidad_censo_sin_vincular', methods: ['GET'])]
    public function verCensoSinVincular(int $id): JsonResponse
    {
        $entidad = $this->entidadRepository->find($id);

        if (!$entidad) {
            return $this->json(['error' => 'Entidad no encontrada'], 404);
        }

        $entradas = $this->censoRepository->findSinVincularByEntidad($entidad);

        $data = array_map(fn(CensoEntrada $entrada) => [
            'id' => $entrada->getId(),
            'nombre' => $entrada->getNombre(),
            'apellidos' => $entrada->getApellidos(),
            'email' => $entrada->getEmail(),
            'dni' => $entrada->getDni(),
            'parentesco' => $entrada->getParentesco(),
            'tipoPersona' => $entrada->getTipoPersona()->value,
            'tipoRelacionEconomica' => $entrada->getTipoRelacionEconomica()->value,
            'temporada' => $entrada->getTemporada(),
        ], $entradas);

        return $this->json([
            'total' => count($data),
            'entradas' => $data,
        ]);
    }
}
