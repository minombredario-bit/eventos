<?php
declare(strict_types=1);

namespace App\Controller;

use App\Enum\MetodoPagoEnum;
use App\Enum\TipoPersonaEnum;
use App\Enum\TipoRelacionEnum;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[Route('/api/generic')]
final class GenericEnumController extends AbstractController
{
    /**
     * Mapa de enums expuestos públicamente.
     * La key es lo que recibe el frontend en la URL.
     *
     * @var array<string, class-string>
     */
    private const ENUM_MAP = [
        'tipo-relacion' => TipoRelacionEnum::class,
        'metodo-pago' => MetodoPagoEnum::class,
        'tipo-persona' => TipoPersonaEnum::class,
    ];

    #[Route('/enums/{enumName}', name: 'generic_enum_choices', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    #[IsGranted('ROLE_ADMIN_ENTIDAD')]
    public function __invoke(string $enumName): JsonResponse
    {
        $enumClass = self::ENUM_MAP[$enumName] ?? null;

        if ($enumClass === null || !enum_exists($enumClass)) {
            throw new NotFoundHttpException(sprintf('Enum "%s" no disponible.', $enumName));
        }

        $items = array_map(
            static function ($case): array {
                return [
                    'name' => $case->name,
                    'value' => $case->value,
                    'label' => method_exists($case, 'label') ? $case->label() : $case->name,
                ];
            },
            $enumClass::cases()
        );

        return new JsonResponse([
            'enum' => $enumName,
            'items' => $items,
        ]);
    }
}
