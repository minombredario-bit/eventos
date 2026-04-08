# Skill: Backend — Symfony 7.3 + API Platform 4.2.6

Lee este archivo completo antes de generar cualquier código de backend.

---

## ⚠️ Restricciones de edición

- Modifica **solo** los archivos mencionados en la tarea
- **No alteres** entidades existentes más allá de lo pedido
- **No toques** `composer.json`, `symfony.lock`, `config/`, `security.yaml`, `services.yaml` sin indicación
- **No generes ni modifiques** migraciones salvo que se pida explícitamente
- **No renombres** clases, métodos ni propiedades existentes
- Si necesitas un archivo no mencionado, **para y pregunta** antes de editarlo

---

## Stack y versiones

| Paquete | Versión |
|---|---|
| PHP | 8.3+ |
| Symfony | 7.3 |
| API Platform | 4.2.6 |
| LexikJWTAuthenticationBundle | 3.x |
| PhpSpreadsheet | 2.x |
| DomPDF | 2.x |
| Doctrine ORM | 3.x |
| PHPUnit | 11.x |

---

## ⚠️ Cambios importantes en API Platform 4.x (vs 3.x)

API Platform 4 introduce breaking changes respecto a la versión 3. Aplica siempre estas convenciones:

- El namespace de metadata es `ApiPlatform\Metadata\` (igual que en 3.x, no cambia)
- Los **State Processors** implementan `ProcessorInterface` de `ApiPlatform\State\ProcessorInterface`
- Los **State Providers** implementan `ProviderInterface` de `ApiPlatform\State\ProviderInterface`
- Ya no existe `DataTransformerInterface` ni `DataPersisterInterface` — usa siempre Processors/Providers
- La configuración de operaciones usa atributos PHP, nunca YAML
- `#[ApiFilter]` y `#[ApiProperty]` siguen en el mismo namespace
- Para paginación personalizada usa `PaginatorInterface` de `ApiPlatform\Doctrine\Orm\Paginator`

---

## Estructura de directorios

```
backend/src/
├── Entity/               # Entidades Doctrine
├── Enum/                 # PHP 8.1 backed enums
├── Repository/           # Repositorios custom
├── Service/              # Lógica de negocio
├── Controller/           # Controllers no-API-Platform (reportes, imports)
├── State/                # State processors y providers (API Platform 4)
│   ├── Processor/
│   └── Provider/
├── Security/             # Voters y authenticators
├── EventSubscriber/      # Suscriptores de eventos Symfony/Doctrine
├── DataFixtures/         # Fixtures de desarrollo
└── Dto/                  # DTOs para inputs/outputs de API Platform
```

---

## Entidades principales

Genera cada entidad en `src/Entity/`. Usa atributos PHP, no YAML ni XML.

### Convenciones de entidad

```php
<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Patch;
use App\Enum\EstadoValidacionEnum;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: UsuarioRepository::class)]
#[ORM\Table(name: 'usuario')]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    normalizationContext: ['groups' => ['usuario:read']],
    denormalizationContext: ['groups' => ['usuario:write']],
    operations: [
        new Get(security: "is_granted('VIEW', object)"),
        new GetCollection(security: "is_granted('ROLE_ADMIN_FALLA')"),
        new Post(security: "is_granted('ROLE_SUPERADMIN')"),
        new Patch(security: "is_granted('EDIT', object)"),
    ]
)]
class Usuario implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    #[ORM\Column(type: 'uuid')]
    #[Groups(['usuario:read'])]
    private ?string $id = null;

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
```

### Enums (uno por archivo en src/Enum/)

```php
<?php

namespace App\Enum;

enum EstadoValidacionEnum: string
{
    case PENDIENTE_VALIDACION = 'pendiente_validacion';
    case VALIDADO             = 'validado';
    case RECHAZADO            = 'rechazado';
    case BLOQUEADO            = 'bloqueado';

    public function label(): string
    {
        return match($this) {
            self::PENDIENTE_VALIDACION => 'Pendiente de validación',
            self::VALIDADO             => 'Validado',
            self::RECHAZADO            => 'Rechazado',
            self::BLOQUEADO            => 'Bloqueado',
        };
    }
}
```

Enums del proyecto:

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

## Servicios críticos

### PriceCalculatorService

`src/Service/PriceCalculatorService.php` — servicio más importante del sistema.

**Reglas de cálculo (no modificar sin autorización explícita):**

```
1. Si esDePago = false → precio = 0.00
2. Si esDePago = true:
   a. Si tipoMenu = INFANTIL → precioInfantil ?? precioBase
   b. Si tipoMenu = ADULTO o ESPECIAL:
      - INTERNO + VALIDADO → precioAdultoInterno ?? precioBase
      - Cualquier otro caso → precioAdultoExterno ?? precioBase
3. Nunca retornar null; siempre decimal(8,2)
```

```php
<?php

namespace App\Service;

use App\Entity\MenuEvento;
use App\Entity\PersonaFamiliar;
use App\Enum\TipoMenuEnum;
use App\Enum\TipoRelacionEconomicaEnum;
use App\Enum\EstadoValidacionEnum;

class PriceCalculatorService
{
    public function calcularPrecio(MenuEvento $menu, PersonaFamiliar $persona): float
    {
        if (!$menu->isEsDePago()) {
            return 0.00;
        }

        if ($menu->getTipoMenu() === TipoMenuEnum::INFANTIL) {
            return (float) ($menu->getPrecioInfantil() ?? $menu->getPrecioBase());
        }

        $esInterno = $persona->getTipoRelacionEconomica() === TipoRelacionEconomicaEnum::INTERNO
            && $persona->getEstadoValidacion() === EstadoValidacionEnum::VALIDADO;

        if ($esInterno) {
            return (float) ($menu->getPrecioAdultoInterno() ?? $menu->getPrecioBase());
        }

        return (float) ($menu->getPrecioAdultoExterno() ?? $menu->getPrecioBase());
    }

    public function calcularTotalInscripcion(array $lineas): float
    {
        return array_reduce($lineas, fn($carry, $linea) => $carry + $linea->getPrecioUnitario(), 0.00);
    }
}
```

### CensoImporterService

`src/Service/CensoImporterService.php` — usa PhpSpreadsheet.

- Leer Excel fila a fila
- Detectar columnas por nombre de cabecera (no por posición)
- Normalizar strings (trim, lowercase para matching)
- Validar columnas obligatorias: `nombre`, `apellidos`
- Crear entidades `CensoEntrada` para la falla
- Retornar: `{ total, insertadas, errores: [{ fila, motivo }] }`

Columnas esperadas:

```
nombre | apellidos | email | dni | parentesco | tipo_persona | tipo_relacion
```

### CensoMatcherService

`src/Service/CensoMatcherService.php`

```php
public function buscarCoincidencia(Falla $falla, string $email, ?string $dni): MatchResult
{
    // 1. Buscar por email (case-insensitive)
    // 2. Si no hay resultado, buscar por DNI si se proporcionó
    // 3. Más de una coincidencia → MatchResult::MULTIPLE
    // 4. Exactamente una → MatchResult::FOUND con la entidad
    // 5. Ninguna → MatchResult::NOT_FOUND
}
```

### InscripcionService

`src/Service/InscripcionService.php`

- Validar que el evento está abierto (estado y fechas)
- Validar que cada persona pertenece al usuario autenticado
- Validar que cada menú pertenece al evento y está activo
- Validar que no hay duplicado de persona en el evento
- Calcular precio de cada línea usando `PriceCalculatorService`
- Crear snapshots en cada `InscripcionLinea`
- Calcular `importeTotal`
- Determinar `estadoPago` automáticamente
- Generar código único de inscripción

### CredencialService

`src/Service/CredencialService.php`

- Verificar que la inscripción está confirmada
- Verificar que el evento requiere credencial
- Verificar que la hora actual (UTC del servidor, nunca del cliente) está dentro de la ventana configurada
- Verificar que el pago no bloquea la credencial
- Generar token temporal firmado válido para esa ventana

---

## State Processors y Providers (API Platform 4)

En API Platform 4 usa `src/State/Processor/` y `src/State/Provider/`.
**No uses** `DataPersisterInterface` ni `DataTransformerInterface` (eliminados en v4).

```php
// src/State/Processor/InscripcionProcessor.php

use ApiPlatform\State\ProcessorInterface;
use ApiPlatform\Metadata\Operation;

class InscripcionProcessor implements ProcessorInterface
{
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        // lógica de persistencia
    }
}
```

---

## Endpoints personalizados (fuera de API Platform CRUD)

Usa controllers Symfony normales para operaciones que no encajan en REST puro.

```php
// src/Controller/Admin/ValidarUsuarioController.php

#[Route('/api/admin/usuarios/{id}/validar', methods: ['POST'])]
#[IsGranted('ROLE_ADMIN_FALLA')]
public function validar(Usuario $usuario, EntityManagerInterface $em): JsonResponse
{
    // 1. Comprobar que el usuario pertenece a la falla del admin (voter)
    // 2. Cambiar estadoValidacion a VALIDADO
    // 3. Registrar validadoPor y fechaValidacion
    // 4. Guardar
    // 5. Retornar 200 con el usuario serializado
}
```

---

## Seguridad y voters

Crear voters en `src/Security/Voter/`:

- `FallaVoter` — verifica que el recurso pertenece a la falla del admin autenticado
- `InscripcionVoter` — verifica que la inscripción pertenece al usuario autenticado
- `PersonaFamiliarVoter` — verifica que el familiar pertenece al usuario autenticado
- `EventoVoter` — VIEW público, EDIT/DELETE solo admin de la falla

```php
<?php

namespace App\Security\Voter;

use App\Entity\Evento;
use App\Entity\Usuario;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class EventoVoter extends Voter
{
    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, ['VIEW', 'EDIT', 'DELETE'])
            && $subject instanceof Evento;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof Usuario) {
            return false;
        }

        return match($attribute) {
            'VIEW'           => true,
            'EDIT', 'DELETE' => $this->puedeEditar($subject, $user),
            default          => false,
        };
    }

    private function puedeEditar(Evento $evento, Usuario $user): bool
    {
        return $user->getFalla() === $evento->getFalla()
            && in_array('ROLE_ADMIN_FALLA', $user->getRoles());
    }
}
```

---

## Importación del censo (Excel)

Columnas del Excel:

| Columna | Obligatoria | Valor por defecto |
|---|---|---|
| nombre | sí | — |
| apellidos | sí | — |
| email | no | null |
| dni | no | null |
| parentesco | no | "otro" |
| tipo_persona | no | "adulto" |
| tipo_relacion | no | "interno" |

El endpoint debe:

1. Recibir archivo `multipart/form-data`
2. Validar que es `.xlsx` o `.xls`
3. Leer cabecera y mapear columnas por nombre (no por posición)
4. Procesar fila a fila capturando errores sin abortar el proceso
5. Retornar: `{ total: 150, insertadas: 148, errores: [{ fila: 23, motivo: "..." }] }`

---

## Generación de reportes

### Excel (PhpSpreadsheet)

```php
// src/Service/Reporte/ReporteMenuService.php

public function generarExcel(Evento $evento): Response
{
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->fromArray(['Menú', 'Adultos', 'Infantiles', 'Total'], null, 'A1');

    $writer = new Xlsx($spreadsheet);
    $response = new StreamedResponse(fn() => $writer->save('php://output'));
    $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    $response->headers->set('Content-Disposition', 'attachment; filename="reporte-menu.xlsx"');

    return $response;
}
```

### PDF (DomPDF)

```php
// src/Service/Reporte/ReportePdfService.php

public function generarPdf(Evento $evento, string $tipoReporte): Response
{
    $html = $this->twig->render('reporte/' . $tipoReporte . '.html.twig', [
        'evento' => $evento,
        'datos'  => $this->getDatos($evento, $tipoReporte),
    ]);

    $dompdf = new Dompdf();
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'landscape');
    $dompdf->render();

    return new Response($dompdf->output(), 200, [
        'Content-Type'        => 'application/pdf',
        'Content-Disposition' => 'attachment; filename="reporte.pdf"',
    ]);
}
```

---

## Fixtures de desarrollo

Crear en `src/DataFixtures/`:

- `FallaFixtures` — 2 fallas de ejemplo
- `CensoFixtures` — 20 entradas de censo por falla
- `UsuarioFixtures` — superadmin, 2 admins, 10 usuarios por falla
- `EventoFixtures` — 5 eventos por falla con distintos estados
- `MenuFixtures` — 2-3 menús por evento
- `InscripcionFixtures` — inscripciones variadas

---

## Tests

```
backend/tests/
├── Unit/
│   ├── Service/PriceCalculatorServiceTest.php
│   ├── Service/CensoMatcherServiceTest.php
│   └── Service/InscripcionServiceTest.php
├── Functional/
│   ├── Api/RegistroTest.php
│   ├── Api/InscripcionTest.php
│   └── Api/AdminValidacionTest.php
└── bootstrap.php
```

- Tests de servicio: unitarios (sin base de datos)
- Tests de API: `ApiTestCase` de API Platform con SQLite en memoria

---

## Migraciones

```bash
php bin/console doctrine:migrations:diff     # generar tras cambiar entidades
php bin/console doctrine:migrations:migrate  # aplicar
php bin/console doctrine:migrations:status   # estado actual
```

**Nunca editar migraciones ya ejecutadas.** Crear siempre una nueva.

---

## Checklist antes de hacer PR

- [ ] Todos los precios se calculan en `PriceCalculatorService`, nunca en el controller
- [ ] Los endpoints de admin comprueban que el recurso pertenece a la falla del usuario (voter)
- [ ] Los endpoints de superadmin requieren `ROLE_SUPERADMIN`
- [ ] Los snapshots de `InscripcionLinea` se rellenan en el momento de la inscripción
- [ ] La hora del servidor se usa para validar credenciales, nunca la del cliente
- [ ] Las migraciones están generadas y revisadas
- [ ] Los tests unitarios de servicios críticos están actualizados
- [ ] Se usan State Processors/Providers de API Platform 4 (no DataPersisters)
