<?php

namespace App\Controller\Api;

use App\Entity\Usuario;
use App\Entity\Entidad;
use App\Entity\CensoEntrada;
use App\Enum\CensadoViaEnum;
use App\Enum\EstadoValidacionEnum;
use App\Enum\TipoRelacionEconomicaEnum;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/registro')]
class RegistroController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
    ) {}

    /**
     * Valida un código de entidad y devuelve su información.
     */
    #[Route('/validar-codigo', name: 'registro_validar_codigo', methods: ['POST'])]
    public function validarCodigo(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $codigo = $data['codigo'] ?? '';

        $entidad = $this->entityManager->getRepository(Entidad::class)
            ->findOneBy(['codigoRegistro' => $codigo, 'activa' => true]);

        if (!$entidad) {
            return new JsonResponse([
                'valido' => false,
                'mensaje' => 'Código de registro inválido',
            ]);
        }

        return new JsonResponse([
            'valido' => true,
            'entidad' => [
                'id' => $entidad->getId(),
                'nombre' => $entidad->getNombre(),
                'tipoEntidad' => $entidad->getTipoEntidad()->value,
                'terminologiaSocio' => $entidad->getTerminologiaSocio(),
                'terminologiaEvento' => $entidad->getTerminologiaEvento(),
                'logo' => $entidad->getLogo(),
            ],
        ]);
    }

    /**
     * Solicita el registro de un nuevo usuario.
     */
    #[Route('/solicitud', name: 'registro_solicitud', methods: ['POST'])]
    public function solicitud(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        // Validaciones básicas
        if (empty($data['email']) || empty($data['password']) || empty($data['entidadId'])) {
            return new JsonResponse([
                'error' => 'Faltan datos obligatorios'
            ], 400);
        }

        // Verificar que el email no esté registrado
        $existe = $this->entityManager->getRepository(Usuario::class)
            ->findOneBy(['email' => $data['email']]);

        if ($existe) {
            return new JsonResponse([
                'error' => 'El email ya está registrado'
            ], 400);
        }

        // Obtener la entidad
        $entidad = $this->entityManager->find(Entidad::class, $data['entidadId']);
        if (!$entidad) {
            return new JsonResponse([
                'error' => 'Entidad no encontrada'
            ], 400);
        }

        // Buscar coincidencia en el censo
        $censoCoincidencia = $this->buscarCoincidenciaCenso($data['email'], $data['dni'] ?? null, $entidad);

        // Crear el usuario
        $usuario = new Usuario();
        $usuario->setEntidad($entidad);
        $usuario->setNombre($data['nombre'] ?? '');
        $usuario->setApellidos($data['apellidos'] ?? '');
        $usuario->setEmail($data['email']);
        $usuario->setTelefono($data['telefono'] ?? null);
        $usuario->setPassword($this->passwordHasher->hashPassword($usuario, $data['password']));
        $usuario->setRoles(['ROLE_USER']);
        $usuario->setActivo(true);
        $usuario->setFechaSolicitudAlta(new \DateTimeImmutable());
        $usuario->setCodigoRegistroUsado($data['codigoRegistro'] ?? null);

        // Determinar estado según coincidencia de censo
        if ($censoCoincidencia === 'unica') {
            // Coincidencia única: auto-validar
            $usuario->setEstadoValidacion(EstadoValidacionEnum::VALIDADO);
            $usuario->setEsCensadoInterno(true);
            $usuario->setTipoUsuarioEconomico(TipoRelacionEconomicaEnum::INTERNO);
            $usuario->setCensadoVia(CensadoViaEnum::EXCEL);
            $usuario->setFechaValidacion(new \DateTimeImmutable());
            $usuario->setFechaAltaCenso(new \DateTimeImmutable());
        } elseif ($censoCoincidencia === 'multiple') {
            // Coincidencia múltiple: pendiente
            $usuario->setEstadoValidacion(EstadoValidacionEnum::PENDIENTE_VALIDACION);
            $usuario->setEsCensadoInterno(false);
            $usuario->setTipoUsuarioEconomico(TipoRelacionEconomicaEnum::EXTERNO);
            $usuario->setCensadoVia(CensadoViaEnum::EXCEL);
        } else {
            // Sin coincidencia: pendiente manual
            $usuario->setEstadoValidacion(EstadoValidacionEnum::PENDIENTE_VALIDACION);
            $usuario->setEsCensadoInterno(false);
            $usuario->setTipoUsuarioEconomico(TipoRelacionEconomicaEnum::EXTERNO);
            $usuario->setCensadoVia(CensadoViaEnum::MANUAL);
        }

        $this->entityManager->persist($usuario);
        $this->entityManager->flush();

        return new JsonResponse([
            'usuarioId' => $usuario->getId(),
            'validado' => $usuario->getEstadoValidacion() === EstadoValidacionEnum::VALIDADO,
            'mensaje' => $usuario->getEstadoValidacion() === EstadoValidacionEnum::VALIDADO
                ? 'Usuario registrado y validado automáticamente'
                : 'Usuario registrado. Pendiente de validación por el administrador.',
        ], 201);
    }

    /**
     * Busca coincidencia en el censo por email o DNI.
     */
    private function buscarCoincidenciaCenso(string $email, ?string $dni, Entidad $entidad): ?string
    {
        $qb = $this->entityManager->createQueryBuilder()
            ->select('COUNT(c.id)')
            ->from(CensoEntrada::class, 'c')
            ->where('c.entidad = :entidad')
            ->andWhere('c.procesado = :procesado')
            ->andWhere('c.temporada = :temporada')
            ->andWhere('c.email = :email')
            ->setParameter('entidad', $entidad)
            ->setParameter('procesado', false)
            ->setParameter('temporada', $entidad->getTemporadaActual())
            ->setParameter('email', strtolower(trim($email)));

        // Si hay DNI, buscar también por DNI
        if ($dni) {
            $qb->orWhere('c.dni = :dni')
               ->setParameter('dni', strtoupper(trim($dni)));
        }

        $count = (int) $qb->getQuery()->getSingleScalarResult();

        if ($count === 1) {
            return 'unica';
        } elseif ($count > 1) {
            return 'multiple';
        }

        return null;
    }
}
