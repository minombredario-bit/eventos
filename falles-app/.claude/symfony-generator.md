# Skill: Symfony Generator — FestApp Backend

Lee este archivo completo antes de generar cualquier pieza de código de backend.

---

## ⚠️ Restricciones globales

- Genera **solo** lo que se pide. No añadas archivos extra "por si acaso".
- **No modifiques** entidades, enums ni servicios existentes más allá de lo pedido.
- **No toques** `composer.json`, `symfony.lock`, `config/`, `security.yaml`, `services.yaml` sin indicación explícita.
- **No generes ni modifiques** migraciones salvo que se pida expresamente.
- **No renombres** clases, métodos ni propiedades ya definidas.
- Si necesitas un archivo no mencionado, **para y pregunta** antes de editarlo.
- Declara siempre qué archivos vas a crear/modificar antes de escribir código.

---

## Stack y versiones

| Paquete | Versión |
|---|---|
| PHP | 8.3+ |
| Symfony | 7.3 |
| API Platform | 4.2.6 |
| LexikJWTAuthenticationBundle | 3.x |
| Doctrine ORM | 3.x |
| PhpSpreadsheet | 2.x |
| DomPDF | 2.x |
| PHPUnit | 11.x |

---

## Reglas base — aplicar siempre

- **Atributos PHP** — nunca YAML ni XML para mapping Doctrine, seguridad ni rutas
- **PHP 8.3** — usar `readonly` properties, enums, named arguments, match expressions y union types donde aplique
- **API Platform 4** — State Processors/Providers en `src/State/`; `DataPersisterInterface` y `DataTransformerInterface` no existen
- **Serialización** — siempre mediante grupos `#[Groups([...])]`; nunca serializar datos sensibles (passwords, tokens internos)
- **Precios** — siempre calculados en `PriceCalculatorService`; nunca en controllers ni processors
- **Hora del servidor** — para credenciales y ventanas temporales usar `new \DateTimeImmutable()` del servidor, nunca datos del cliente
- **Voters** — cualquier operación sobre un recurso ajeno pasa por un voter; nunca comparar IDs a mano en el controller
- **Snapshots** — los campos `*Snapshot` de `InscripcionLinea` se rellenan en el momento de la inscripción y nunca se actualizan después

---

## Estructura de directorios

```
backend/src/
├── Entity/               # Entidades Doctrine
├── Enum/                 # PHP 8.1 backed enums
├── Repository/           # Repositorios custom
├── Service/              # Lógica de negocio
│   └── Reporte/          # Servicios de generación de reportes
├── Controller/           # Controllers Symfony normales (no API Platform)
│   └── Admin/            # Endpoints de administración
├── State/                # API Platform 4 — processors y providers
│   ├── Processor/
│   └── Provider/
├── Dto/                  # DTOs de entrada/salida para API Platform
├── Security/
│   └── Voter/            # Voters de autorización
├── EventSubscriber/      # Suscriptores de eventos Symfony/Doctrine
└── DataFixtures/         # Fixtures de desarrollo
```

---

## 1. ENTIDADES

### Plantilla base de entidad

```php
<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Delete;
use App\Repository\{Entidad}Repository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: {Entidad}Repository::class)]
#[ORM\Table(name: '{tabla}')]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    normalizationContext: ['{entidad}:read'],
    denormalizationContext: ['{entidad}:write'],
    operations: [
        new Get(security: "is_granted('VIEW', object)"),
        new GetCollection(security: "is_granted('ROLE_USER')"),
        new Post(security: "is_granted('ROLE_ADMIN_FALLA')"),
        new Patch(
            security: "is_granted('EDIT', object)",
            inputFormats: ['json' => ['application/merge-patch+json']],
        ),
        new Delete(security: "is_granted('DELETE', object)"),
    ]
)]
class {Entidad}
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['{entidad}:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    #[Groups(['{entidad}:read', '{entidad}:write'])]
    private ?string $nombre = null;

    #[ORM\Column]
    #[Groups(['{entidad}:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['{entidad}:read'])]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getNombre(): ?string { return $this->nombre; }
    public function setNombre(string $nombre): static { $this->nombre = $nombre; return $this; }

    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): ?\DateTimeImmutable { return $this->updatedAt; }
}
```

### Convenciones de entidad

- **IDs**: `int` autoincremental por defecto; UUID solo para entidades expuestas públicamente sin autenticación (credenciales, tokens)
- **Timestamps**: `createdAt` y `updatedAt` como `\DateTimeImmutable` con lifecycle callbacks `#[ORM\PrePersist]` / `#[ORM\PreUpdate]`
- **Nullable**: propiedades opcionales como `?string`, nunca `string` sin inicializar
- **Fluent setters**: retornar `static` para encadenamiento
- **Colecciones Doctrine**: inicializar en el constructor con `new ArrayCollection()`
- **Grupos de serialización**: patrón `{entidad}:read` / `{entidad}:write`; usar `{entidad}:admin` para campos solo visibles por admins

### Relaciones Doctrine

```php
// ManyToOne (lado propietario)
#[ORM\ManyToOne(targetEntity: Falla::class, inversedBy: 'eventos')]
#[ORM\JoinColumn(nullable: false)]
#[Groups(['{entidad}:read'])]
private ?Falla $falla = null;

// OneToMany (lado inverso — siempre inicializar en constructor)
#[ORM\OneToMany(mappedBy: 'evento', targetEntity: MenuEvento::class, cascade: ['persist', 'remove'])]
#[Groups(['{entidad}:read'])]
private Collection $menus;

public function __construct()
{
    $this->menus = new ArrayCollection();
}

// ManyToMany
#[ORM\ManyToMany(targetEntity: Tag::class)]
#[ORM\JoinTable(name: '{entidad}_tag')]
private Collection $tags;
```

### Entidad con UUID (para acceso público sin auth)

```php
use Symfony\Component\Uid\Uuid;
use Doctrine\ORM\Id\UuidGenerator;

#[ORM\Id]
#[ORM\GeneratedValue(strategy: 'CUSTOM')]
#[ORM\CustomIdGenerator(class: UuidGenerator::class)]
#[ORM\Column(type: 'uuid')]
#[Groups(['{entidad}:read'])]
private ?Uuid $id = null;
```

---

## 2. ENUMS

```php
<?php

namespace App\Enum;

enum {Nombre}Enum: string
{
    case CASO_A = 'caso_a';
    case CASO_B = 'caso_b';

    public function label(): string
    {
        return match($this) {
            self::CASO_A => 'Caso A',
            self::CASO_B => 'Caso B',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
```

Mapeo en entidad:

```php
#[ORM\Column(type: 'string', enumType: {Nombre}Enum::class)]
#[Groups(['{entidad}:read', '{entidad}:write'])]
private {Nombre}Enum $estado = {Nombre}Enum::CASO_A;
```

Enums del proyecto (no recrear, ya existen):

| Enum | Casos |
|---|---|
| `TipoEventoEnum` | ALMUERZO, COMIDA, MERIENDA, CENA, OTRO |
| `TipoPersonaEnum` | ADULTO, INFANTIL |
| `TipoRelacionEconomicaEnum` | INTERNO, EXTERNO, INVITADO |
| `EstadoValidacionEnum` | PENDIENTE_VALIDACION, VALIDADO, RECHAZADO, BLOQUEADO |
| `TipoMenuEnum` | ADULTO, INFANTIL, ESPECIAL, LIBRE |
| `EstadoEventoEnum` | BORRADOR, PUBLICADO, CERRADO, FINALIZADO, CANCELADO |
| `EstadoInscripcionEnum` | PENDIENTE, CONFIRMADA, CANCELADA, LISTA_ESPERA |
| `EstadoPagoEnum` | NO_REQUIERE_PAGO, PENDIENTE, PARCIAL, PAGADO, DEVUELTO, CANCELADO |
| `MetodoPagoEnum` | EFECTIVO, TRANSFERENCIA, BIZUM, TPV, ONLINE, MANUAL |
| `EstadoLineaInscripcionEnum` | PENDIENTE, CONFIRMADA, CANCELADA |
| `CensadoViaEnum` | EXCEL, MANUAL, INVITACION |

---

## 3. REPOSITORIOS

```php
<?php

namespace App\Repository;

use App\Entity\{Entidad};
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<{Entidad}>
 */
class {Entidad}Repository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, {Entidad}::class);
    }

    /**
     * @return {Entidad}[]
     */
    public function findByFalla(int $fallaId): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.falla = :fallaId')
            ->setParameter('fallaId', $fallaId)
            ->orderBy('e.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Búsqueda case-insensitive sin tildes usando LOWER()
     */
    public function findByEmailNormalizado(string $email): ?{Entidad}
    {
        return $this->createQueryBuilder('e')
            ->andWhere('LOWER(e.email) = LOWER(:email)')
            ->setParameter('email', $email)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
```

### Reglas de repositorio

- Métodos de consulta siempre con `@return` tipado en PHPDoc
- Usar `createQueryBuilder` para consultas complejas; `findOneBy` / `findBy` para simples
- Nunca lógica de negocio en repositorios — solo acceso a datos
- Filtros por falla siempre presentes en consultas de recursos de admin

---

## 4. SERVICIOS

### Plantilla base de servicio

```php
<?php

namespace App\Service;

use App\Entity\{Entidad};
use App\Repository\{Entidad}Repository;
use Doctrine\ORM\EntityManagerInterface;

class {Nombre}Service
{
    public function __construct(
        private readonly {Entidad}Repository $repository,
        private readonly EntityManagerInterface $em,
    ) {}

    public function procesar({Entidad} $entidad): void
    {
        // lógica de negocio
        $this->em->persist($entidad);
        $this->em->flush();
    }
}
```

### Reglas de servicio

- **Constructor injection** con `readonly` — nunca `@required` ni setter injection
- Un servicio hace **una sola cosa** — si crece demasiado, extraer sub-servicios
- La lógica de precios va **siempre** en `PriceCalculatorService`, nunca en otros servicios ni controllers
- Usar `EntityManagerInterface` solo en servicios que persisten datos; los que solo leen usan el repositorio directamente
- Las excepciones de dominio se lanzan como `\RuntimeException` o excepciones custom en `src/Exception/`

### Servicio con resultado tipado

Para operaciones que pueden tener múltiples resultados (match, import):

```php
<?php

namespace App\Service;

// Objeto resultado tipado — evita retornar arrays sin tipo
final class MatchResult
{
    private function __construct(
        public readonly string $status,         // 'found' | 'not_found' | 'multiple'
        public readonly mixed  $entity = null,
    ) {}

    public static function found(mixed $entity): self
    {
        return new self('found', $entity);
    }

    public static function notFound(): self
    {
        return new self('not_found');
    }

    public static function multiple(): self
    {
        return new self('multiple');
    }

    public function isFound(): bool    { return $this->status === 'found'; }
    public function isNotFound(): bool { return $this->status === 'not_found'; }
    public function isMultiple(): bool { return $this->status === 'multiple'; }
}
```

---

## 5. STATE PROCESSORS (API Platform 4)

Usar para lógica personalizada en operaciones POST, PUT, PATCH, DELETE.
**Nunca usar `DataPersisterInterface`** — fue eliminado en API Platform 4.

```php
<?php

namespace App\State\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\{Entidad};
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * @implements ProcessorInterface<{Entidad}, {Entidad}>
 */
class {Nombre}Processor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Security              $security,
        // inyectar el processor de persistencia de API Platform para no perder funcionalidad base
        private readonly ProcessorInterface    $persistProcessor,
    ) {}

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        // lógica personalizada antes de persistir
        // ...

        // delegar la persistencia al processor base de API Platform
        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }
}
```

Registrar en la entidad:

```php
#[ApiResource(
    operations: [
        new Post(processor: {Nombre}Processor::class),
        new Patch(processor: {Nombre}Processor::class),
    ]
)]
```

---

## 6. STATE PROVIDERS (API Platform 4)

Usar para personalizar cómo se obtienen los datos (GET, GetCollection).
**Nunca usar `DataProviderInterface`** — fue eliminado en API Platform 4.

```php
<?php

namespace App\State\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\{Entidad};
use App\Repository\{Entidad}Repository;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * @implements ProviderInterface<{Entidad}>
 */
class {Nombre}Provider implements ProviderInterface
{
    public function __construct(
        private readonly {Entidad}Repository $repository,
        private readonly Security            $security,
    ) {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $usuario = $this->security->getUser();

        // Filtrar automáticamente por falla del usuario autenticado
        return $this->repository->findByFalla($usuario->getFalla()->getId());
    }
}
```

---

## 7. DTOs

Usar cuando la entrada o salida de la API difiere de la entidad.

```php
<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class {Nombre}Input
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 255)]
        public readonly string $campo,

        #[Assert\Email]
        public readonly ?string $email = null,
    ) {}
}
```

```php
// DTO de salida (solo lectura)
final class {Nombre}Output
{
    public function __construct(
        public readonly int    $id,
        public readonly string $campo,
        public readonly string $createdAt,
    ) {}
}
```

Vincular al recurso:

```php
#[ApiResource(
    operations: [
        new Post(
            input:     {Nombre}Input::class,
            output:    {Nombre}Output::class,
            processor: {Nombre}Processor::class,
        ),
    ]
)]
```

---

## 8. CONTROLLERS PERSONALIZADOS

Para operaciones que no encajan en el CRUD de API Platform (acciones, imports, reportes).

```php
<?php

namespace App\Controller\Admin;

use App\Entity\{Entidad};
use App\Service\{Nombre}Service;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/admin/{entidades}')]
#[IsGranted('ROLE_ADMIN_FALLA')]
class {Nombre}Controller extends AbstractController
{
    public function __construct(
        private readonly {Nombre}Service $service,
    ) {}

    #[Route('/{id}/accion', name: 'admin_{entidad}_accion', methods: ['POST'])]
    public function accion({Entidad} $entidad): JsonResponse
    {
        // 1. Verificar que el recurso pertenece a la falla del admin (voter)
        $this->denyAccessUnlessGranted('EDIT', $entidad);

        // 2. Ejecutar la acción
        $this->service->ejecutarAccion($entidad);

        return $this->json($entidad, context: ['groups' => ['{entidad}:read']]);
    }

    #[Route('/importar', name: 'admin_{entidad}_importar', methods: ['POST'])]
    public function importar(Request $request): JsonResponse
    {
        $archivo = $request->files->get('archivo');

        if (!$archivo || !in_array($archivo->getClientOriginalExtension(), ['xlsx', 'xls'])) {
            return $this->json(['error' => 'El archivo debe ser .xlsx o .xls'], 422);
        }

        $resultado = $this->service->importar($archivo);

        return $this->json($resultado);
    }
}
```

### Respuestas estándar de controller

```php
// 200 con entidad serializada
return $this->json($entidad, context: ['groups' => ['{entidad}:read']]);

// 201 creado
return $this->json($entidad, 201, context: ['groups' => ['{entidad}:read']]);

// 204 sin contenido (DELETE)
return new Response(null, 204);

// Error de validación
return $this->json(['error' => 'Mensaje de error', 'detalle' => $detalle], 422);

// No encontrado
throw $this->createNotFoundException('Recurso no encontrado');

// Acceso denegado
throw $this->createAccessDeniedException('Sin permisos para esta acción');
```

---

## 9. VOTERS

```php
<?php

namespace App\Security\Voter;

use App\Entity\{Entidad};
use App\Entity\Usuario;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @extends Voter<string, {Entidad}>
 */
class {Entidad}Voter extends Voter
{
    // Atributos soportados
    private const VIEW   = 'VIEW';
    private const EDIT   = 'EDIT';
    private const DELETE = 'DELETE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VIEW, self::EDIT, self::DELETE], true)
            && $subject instanceof {Entidad};
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof Usuario) {
            return false;
        }

        /** @var {Entidad} $entidad */
        $entidad = $subject;

        return match ($attribute) {
            self::VIEW   => $this->puedeVer($entidad, $user),
            self::EDIT   => $this->puedeEditar($entidad, $user),
            self::DELETE => $this->puedeEliminar($entidad, $user),
            default      => false,
        };
    }

    private function puedeVer({Entidad} $entidad, Usuario $user): bool
    {
        // recursos publicados son visibles para todos los usuarios autenticados
        return true;
    }

    private function puedeEditar({Entidad} $entidad, Usuario $user): bool
    {
        // solo el admin de la misma falla
        return $user->getFalla()?->getId() === $entidad->getFalla()?->getId()
            && in_array('ROLE_ADMIN_FALLA', $user->getRoles(), true);
    }

    private function puedeEliminar({Entidad} $entidad, Usuario $user): bool
    {
        return $this->puedeEditar($entidad, $user);
    }
}
```

### Reglas de voter

- Nunca comparar entidades directamente con `===`; comparar por ID
- `VIEW` en recursos de falla: el usuario autenticado debe pertenecer a la misma falla
- `EDIT` / `DELETE`: siempre requiere `ROLE_ADMIN_FALLA` y misma falla
- Operaciones de superadmin: usar directamente `#[IsGranted('ROLE_SUPERADMIN')]` en el controller, sin voter

---

## 10. EVENT SUBSCRIBERS

```php
<?php

namespace App\EventSubscriber;

use App\Entity\{Entidad};
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Events;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;

#[AsDoctrineListener(event: Events::postPersist)]
#[AsDoctrineListener(event: Events::postUpdate)]
class {Nombre}Subscriber
{
    public function postPersist(PostPersistEventArgs $args): void
    {
        $entity = $args->getObject();
        if (!$entity instanceof {Entidad}) {
            return;
        }
        // lógica post-persist
    }

    public function postUpdate(PostUpdateEventArgs $args): void
    {
        $entity = $args->getObject();
        if (!$entity instanceof {Entidad}) {
            return;
        }
        // lógica post-update
    }
}
```

---

## 11. TESTS

### Test unitario de servicio (sin base de datos)

```php
<?php

namespace App\Tests\Unit\Service;

use App\Service\{Nombre}Service;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class {Nombre}ServiceTest extends TestCase
{
    private {Nombre}Service $service;
    private MockObject $repositoryMock;

    protected function setUp(): void
    {
        $this->repositoryMock = $this->createMock({Entidad}Repository::class);
        $this->service = new {Nombre}Service($this->repositoryMock);
    }

    public function test{Accion}_{escenario}_{resultadoEsperado}(): void
    {
        // Arrange
        $entidad = new {Entidad}();
        // configurar el mock
        $this->repositoryMock
            ->expects($this->once())
            ->method('findById')
            ->willReturn($entidad);

        // Act
        $resultado = $this->service->procesar($entidad);

        // Assert
        $this->assertSame('esperado', $resultado);
    }
}
```

### Test funcional de API (con base de datos SQLite)

```php
<?php

namespace App\Tests\Functional\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use ApiPlatform\Symfony\Bundle\Test\Client;
use App\Entity\Usuario;

class {Recurso}Test extends ApiTestCase
{
    private Client $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    public function testGet{Recurso}_autenticado_devuelve200(): void
    {
        $token = $this->autenticar('admin@falla.com', 'password');

        $this->client->request('GET', '/api/{recursos}/1', [
            'auth_bearer' => $token,
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains(['{entidad}:read' => true]);
    }

    public function testGet{Recurso}_sinAutenticar_devuelve401(): void
    {
        $this->client->request('GET', '/api/{recursos}/1');
        $this->assertResponseStatusCodeSame(401);
    }

    private function autenticar(string $email, string $password): string
    {
        $response = $this->client->request('POST', '/api/login_check', [
            'json' => ['email' => $email, 'password' => $password],
        ]);
        return $response->toArray()['token'];
    }
}
```

### Convenciones de naming en tests

```
test{Método}_{escenario}_{resultadoEsperado}

Ejemplos:
testCalcularPrecio_menuGratuito_devuelveCero
testCalcularPrecio_usuarioInterno_aplicaPrecioInterno
testBuscarCoincidencia_emailDuplicado_devuelveMultiple
testImportar_archivoInvalido_lanzaExcepcion
```

---

## 12. FIXTURES

```php
<?php

namespace App\DataFixtures;

use App\Entity\{Entidad};
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class {Entidad}Fixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
    public static function getGroups(): array
    {
        return ['dev'];
    }

    public function getDependencies(): array
    {
        return [
            FallaFixtures::class,
        ];
    }

    public function load(ObjectManager $manager): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $entidad = new {Entidad}();
            $entidad->setNombre("Ejemplo $i");
            $entidad->setFalla($this->getReference("falla-$i", Falla::class));

            $manager->persist($entidad);
            $this->addReference("{entidad}-$i", $entidad);
        }

        $manager->flush();
    }
}
```

### Orden de fixtures (respetar dependencias)

```
1. FallaFixtures
2. CensoFixtures          → depende de Falla
3. UsuarioFixtures         → depende de Falla, Censo
4. EventoFixtures          → depende de Falla
5. MenuFixtures            → depende de Evento
6. InscripcionFixtures     → depende de Usuario, Evento, Menu
```

---

## 13. HELPERS Y UTILS

```php
<?php
// src/Utils/TextoUtils.php

namespace App\Utils;

class TextoUtils
{
    /**
     * Elimina tildes y convierte a minúsculas para comparaciones
     */
    public static function normalizar(string $texto): string
    {
        $texto = mb_strtolower($texto);
        $texto = transliterator_transliterate('Any-Latin; Latin-ASCII', $texto);
        return trim($texto);
    }

    /**
     * Compara dos strings ignorando tildes y mayúsculas
     */
    public static function iguales(string $a, string $b): bool
    {
        return self::normalizar($a) === self::normalizar($b);
    }
}
```

```php
<?php
// src/Utils/CodigoUtils.php

namespace App\Utils;

class CodigoUtils
{
    /**
     * Genera un código único de inscripción
     * Formato: FALLA-YYYYMMDD-XXXXX (ej: FAL-20250301-A3K9P)
     */
    public static function generarCodigoInscripcion(string $prefixFalla): string
    {
        $fecha = (new \DateTimeImmutable())->format('Ymd');
        $sufijo = strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
        return sprintf('%s-%s-%s', strtoupper(substr($prefixFalla, 0, 3)), $fecha, $sufijo);
    }
}
```

```php
<?php
// src/Utils/FechaUtils.php

namespace App\Utils;

class FechaUtils
{
    /**
     * Comprueba si el momento actual (servidor, UTC) está dentro del rango dado
     */
    public static function dentroDeRango(\DateTimeImmutable $inicio, \DateTimeImmutable $fin): bool
    {
        $ahora = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        return $ahora >= $inicio && $ahora <= $fin;
    }

    /**
     * Comprueba si las inscripciones están abiertas para un evento
     */
    public static function inscripcionesAbiertas(\DateTimeImmutable $inicio, \DateTimeImmutable $fin): bool
    {
        return self::dentroDeRango($inicio, $fin);
    }
}
```

---

## 14. CHECKLIST antes de entregar código

### Entidad
- [ ] Atributos PHP (no YAML/XML)
- [ ] `#[ORM\HasLifecycleCallbacks]` con `PrePersist` y `PreUpdate`
- [ ] Colecciones inicializadas en el constructor
- [ ] Grupos de serialización en cada propiedad expuesta
- [ ] Validaciones con `Assert\*` en propiedades escritas desde la API
- [ ] Setters retornan `static` (fluent interface)

### Enum
- [ ] Backed enum con `string` como tipo
- [ ] Método `label()` con textos en español
- [ ] Método estático `values()` para validaciones

### Repositorio
- [ ] Extiende `ServiceEntityRepository<{Entidad}>`
- [ ] PHPDoc tipado en todos los métodos
- [ ] Sin lógica de negocio

### Servicio
- [ ] Constructor con `readonly` properties
- [ ] No usa `EntityManagerInterface` si solo lee datos
- [ ] Precios delegados a `PriceCalculatorService`
- [ ] Excepciones con mensaje descriptivo

### State Processor / Provider (API Platform 4)
- [ ] Implementa `ProcessorInterface` o `ProviderInterface`
- [ ] **No** implementa `DataPersisterInterface` ni `DataProviderInterface`
- [ ] Processor delega persistencia al `$persistProcessor` inyectado

### Controller
- [ ] `#[IsGranted]` en clase o en cada método
- [ ] `denyAccessUnlessGranted()` para recursos individuales
- [ ] Sin lógica de negocio (delega al servicio)
- [ ] Respuestas con `context: ['groups' => [...]]`

### Voter
- [ ] Compara por ID, no por referencia de objeto
- [ ] `supports()` muy específico (clase + atributo)
- [ ] `default => false` en el `match`

### Test
- [ ] Naming: `test{Método}_{escenario}_{resultado}`
- [ ] Tests unitarios: sin base de datos, con mocks
- [ ] Tests funcionales: usan `ApiTestCase` con SQLite
- [ ] Al menos un test por camino feliz y uno por camino de error en servicios críticos
