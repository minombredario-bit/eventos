<?php

namespace App\Controller;

use App\Entity\CensoEntrada;
use App\Entity\Entidad;
use App\Entity\Usuario;
use App\Enum\CensadoViaEnum;
use App\Enum\EstadoValidacionEnum;
use App\Enum\TipoRelacionEconomicaEnum;
use App\Repository\EntidadRepository;
use App\Repository\UsuarioRepository;
use App\Service\CensoMatcherService;
use App\Service\CodeGeneratorService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api')]
class RegistroController extends AbstractController
{
    public function __construct(
        private readonly EntidadRepository $entidadRepository,
        private readonly UsuarioRepository $usuarioRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly CensoMatcherService $censoMatcher,
        private readonly CodeGeneratorService $codeGenerator,
        private readonly ValidatorInterface $validator
    ) {}

    /**
     * Validate an entity registration code.
     * Returns entity info if valid.
     */
    #[Route('/registro/validar-codigo', name: 'api_registro_validar_codigo', methods: ['POST'])]
    public function validarCodigo(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $codigo = $data['codigo'] ?? null;

        if (!$codigo) {
            return $this->json([
                'valido' => false,
                'error' => 'Código no proporcionado',
            ], Response::HTTP_BAD_REQUEST);
        }

        $entidad = $this->entidadRepository->findByCodigoRegistro($codigo);

        if (!$entidad) {
            return $this->json([
                'valido' => false,
                'error' => 'Código de entidad inválido o inactivo',
            ], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
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
     * Submit a registration request.
     * Auto-validates if census match found.
     */
    #[Route('/registro/solicitud', name: 'api_registro_solicitud', methods: ['POST'])]
    public function solicitud(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        // Validate required fields
        $required = ['codigoEntidad', 'nombre', 'apellidos', 'email', 'password'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return $this->json([
                    'error' => "Campo requerido: {$field}",
                ], Response::HTTP_BAD_REQUEST);
            }
        }

        // Find entity
        $entidad = $this->entidadRepository->findByCodigoRegistro($data['codigoEntidad']);
        if (!$entidad) {
            return $this->json([
                'error' => 'Código de entidad inválido',
            ], Response::HTTP_BAD_REQUEST);
        }

        // Check email uniqueness
        $existingUser = $this->usuarioRepository->findByEmail($data['email']);
        if ($existingUser) {
            return $this->json([
                'error' => 'El email ya está registrado',
            ], Response::HTTP_CONFLICT);
        }

        // Try to match with census
        $match = $this->censoMatcher->buscarCoincidencia(
            $entidad,
            $data['email'],
            $data['dni'] ?? null
        );

        // Create user
        $user = new Usuario();
        $user->setEntidad($entidad);
        $user->setNombre($data['nombre']);
        $user->setApellidos($data['apellidos']);
        $user->setEmail($data['email']);
        $user->setTelefono($data['telefono'] ?? null);
        $user->setCodigoRegistroUsado($data['codigoEntidad']);
        $user->setFechaSolicitudAlta(new \DateTimeImmutable());

        // Set password
        $hashedPassword = $this->passwordHasher->hashPassword($user, $data['password']);
        $user->setPassword($hashedPassword);

        // Determine validation status based on census match
        $autoValidado = false;
        if ($match['result'] === CensusMatcherService::MATCH_FOUND) {
            /** @var CensoEntrada $censoEntrada */
            $censoEntrada = $match['entrada'];
            
            // Auto-validate and link to census
            $user->setEstadoValidacion(EstadoValidacionEnum::VALIDADO);
            $user->setEsCensadoInterno(true);
            $user->setCensadoVia(CensadoViaEnum::EXCEL);
            $user->setTipoUsuarioEconomico($censoEntrada->getTipoRelacionEconomica());
            $user->setFechaValidacion(new \DateTimeImmutable());
            
            // Mark census as processed
            $censoEntrada->setUsuarioVinculado($user);
            $censoEntrada->setProcesado(true);
            
            $autoValidado = true;
        } else {
            // Pending admin validation
            $user->setEstadoValidacion(EstadoValidacionEnum::PENDIENTE_VALIDACION);
            $user->setTipoUsuarioEconomico(TipoRelacionEconomicaEnum::EXTERNO);
        }

        // Validate
        $errors = $this->validator->validate($user);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getPropertyPath() . ': ' . $error->getMessage();
            }
            return $this->json(['error' => implode(', ', $errorMessages)], Response::HTTP_BAD_REQUEST);
        }

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $this->json([
            'usuario' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
            ],
            'validadoAutomaticamente' => $autoValidado,
            'mensaje' => $autoValidado
                ? 'Tu cuenta ha sido validada automáticamente'
                : 'Tu solicitud está pendiente de validación por el administrador',
        ], Response::HTTP_CREATED);
    }

    /**
     * Get current user profile.
     */
    #[Route('/me', name: 'api_me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        /** @var Usuario $user */
        $user = $this->getUser();

        return $this->json([
            'id' => $user->getId(),
            'nombre' => $user->getNombre(),
            'apellidos' => $user->getApellidos(),
            'email' => $user->getEmail(),
            'telefono' => $user->getTelefono(),
            'roles' => $user->getRoles(),
            'entidad' => '/api/entidads/' . $user->getEntidad()->getId(),
            'tipoUsuarioEconomico' => $user->getTipoUsuarioEconomico()->value,
            'estadoValidacion' => $user->getEstadoValidacion()->value,
            'esCensadoInterno' => $user->isEsCensadoInterno(),
            'censadoVia' => $user->getCensadoVia()?->value,
            'puedeAcceder' => $user->puedeAcceder(),
            'fechaSolicitudAlta' => $user->getFechaSolicitudAlta()?->format('c'),
            'fechaValidacion' => $user->getFechaValidacion()?->format('c'),
        ]);
    }

    /**
     * Update current user profile.
     */
    #[Route('/me', name: 'api_me_patch', methods: ['PATCH'])]
    public function updateMe(Request $request): JsonResponse
    {
        /** @var Usuario $user */
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true);

        if (isset($data['nombre'])) {
            $user->setNombre($data['nombre']);
        }
        if (isset($data['apellidos'])) {
            $user->setApellidos($data['apellidos']);
        }
        if (isset($data['telefono'])) {
            $user->setTelefono($data['telefono']);
        }

        $this->entityManager->flush();

        return $this->json([
            'id' => $user->getId(),
            'nombre' => $user->getNombre(),
            'apellidos' => $user->getApellidos(),
            'email' => $user->getEmail(),
            'telefono' => $user->getTelefono(),
        ]);
    }
}
