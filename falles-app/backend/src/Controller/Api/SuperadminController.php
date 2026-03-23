<?php

namespace App\Controller\Api;

use App\Entity\Entidad;
use App\Entity\CensoEntrada;
use App\Repository\EntidadRepository;
use App\Repository\CensoEntradaRepository;
use App\Enum\CensadoViaEnum;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/superadmin')]
#[IsGranted('ROLE_SUPERADMIN')]
class SuperadminController extends AbstractController
{
    public function __construct(
        private EntidadRepository $entidadRepository,
        private EntityManagerInterface $entityManager,
    ) {}

    /**
     * Lista todas las entidades.
     */
    #[Route('/entidades', name: 'superadmin_entidades', methods: ['GET'])]
    public function entidades(): JsonResponse
    {
        $entidades = $this->entidadRepository->findAll();

        $data = [];
        foreach ($entidades as $entidad) {
            $data[] = [
                'id' => $entidad->getId(),
                'nombre' => $entidad->getNombre(),
                'slug' => $entidad->getSlug(),
                'tipoEntidad' => $entidad->getTipoEntidad()->value,
                'emailContacto' => $entidad->getEmailContacto(),
                'codigoRegistro' => $entidad->getCodigoRegistro(),
                'temporadaActual' => $entidad->getTemporadaActual(),
                'activa' => $entidad->isActiva(),
                'usuariosCount' => $entidad->getUsuarios()->count(),
                'admins' => array_map(
                    fn($a) => $a->getEmail(),
                    $entidad->getAdmins()->toArray()
                ),
            ];
        }

        return new JsonResponse($data);
    }

    /**
     * Crea una nueva entidad.
     */
    #[Route('/entidades', name: 'superadmin_entidad_create', methods: ['POST'])]
    public function crearEntidad(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $entidad = new Entidad();
        $entidad->setNombre($data['nombre'] ?? '');
        $entidad->setSlug($data['slug'] ?? '');
        $entidad->setEmailContacto($data['emailContacto'] ?? '');
        $entidad->setCodigoRegistro($this->generarCodigoRegistro());
        $entidad->setTemporadaActual($data['temporadaActual'] ?? date('Y'));
        $entidad->setActiva(true);

        if (isset($data['tipoEntidad'])) {
            $entidad->setTipoEntidad(\App\Enum\TipoEntidadEnum::from($data['tipoEntidad']));
        }

        $this->entityManager->persist($entidad);
        $this->entityManager->flush();

        return new JsonResponse([
            'id' => $entidad->getId(),
            'codigoRegistro' => $entidad->getCodigoRegistro(),
        ], 201);
    }

    /**
     * Regenera el código de registro de una entidad.
     */
    #[Route('/entidades/{id}/codigo-registro/regenerar', name: 'superadmin_regenerar_codigo', methods: ['POST'])]
    public function regenerarCodigo(string $id): JsonResponse
    {
        $entidad = $this->entidadRepository->find($id);

        if (!$entidad) {
            return new JsonResponse(['error' => 'Entidad no encontrada'], 404);
        }

        $nuevoCodigo = $this->generarCodigoRegistro();
        $entidad->setCodigoRegistro($nuevoCodigo);
        
        $this->entityManager->flush();

        return new JsonResponse([
            'codigoRegistro' => $nuevoCodigo,
        ]);
    }

    /**
     * Lista las entradas del censo de una entidad.
     */
    #[Route('/entidades/{id}/censo', name: 'superadmin_censo', methods: ['GET'])]
    public function censo(string $id, Request $request): JsonResponse
    {
        $procesado = $request->query->get('procesado');
        
        $em = $this->entityManager;
        $qb = $em->createQueryBuilder()
            ->select('c')
            ->from(CensoEntrada::class, 'c')
            ->where('c.entidad = :entidad')
            ->setParameter('entidad', $id)
            ->orderBy('c.createdAt', 'DESC');

        if ($procesado !== null) {
            $qb->andWhere('c.procesado = :procesado')
               ->setParameter('procesado', $procesado === 'true');
        }

        $censo = $qb->getQuery()->getResult();

        $data = [];
        foreach ($censo as $entrada) {
            $data[] = [
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
                'usuarioVinculado' => $entrada->getUsuarioVinculado()?->getId(),
            ];
        }

        return new JsonResponse($data);
    }

    /**
     * Importa censo desde JSON (preparado para Excel en frontend).
     */
    #[Route('/entidades/{id}/censo/importar', name: 'superadmin_censo_importar', methods: ['POST'])]
    public function importarCenso(string $id, Request $request): JsonResponse
    {
        $entidad = $this->entidadRepository->find($id);

        if (!$entidad) {
            return new JsonResponse(['error' => 'Entidad no encontrada'], 404);
        }

        $data = json_decode($request->getContent(), true);
        $entradas = $data['entradas'] ?? [];
        $temporada = $data['temporada'] ?? date('Y');

        $importadas = 0;
        $errores = [];

        foreach ($entradas as $i => $entradaData) {
            try {
                $censo = new CensoEntrada();
                $censo->setEntidad($entidad);
                $censo->setNombre($entradaData['nombre'] ?? '');
                $censo->setApellidos($entradaData['apellidos'] ?? '');
                $censo->setEmail($entradaData['email'] ?? null);
                $censo->setDni($entradaData['dni'] ?? null);
                $censo->setParentesco($entradaData['parentesco'] ?? 'otro');
                $censo->setTipoPersona(\App\Enum\TipoPersonaEnum::from($entradaData['tipoPersona'] ?? 'adulto'));
                $censo->setTipoRelacionEconomica(\App\Enum\TipoRelacionEconomicaEnum::from($entradaData['tipoRelacionEconomica'] ?? 'interno'));
                $censo->setTemporada($temporada);
                $censo->setCensadoVia(CensadoViaEnum::EXCEL);

                $this->entityManager->persist($censo);
                $importadas++;
            } catch (\Exception $e) {
                $errores[] = "Fila {$i}: " . $e->getMessage();
            }
        }

        $this->entityManager->flush();

        return new JsonResponse([
            'importadas' => $importadas,
            'errores' => $errores,
        ]);
    }

    /**
     * Genera un código de registro seguro.
     */
    private function generarCodigoRegistro(): string
    {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $codigo = '';
        for ($i = 0; $i < 12; $i++) {
            $codigo .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $codigo;
    }
}
