<?php

namespace App\ApiPlatform\Serializer;

use ApiPlatform\State\SerializerContextBuilderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\SecurityBundle\Security;
use App\Entity\Evento;

final class CustomSerializerContextBuilder implements SerializerContextBuilderInterface
{
    public function __construct(
        private readonly SerializerContextBuilderInterface $decorated,
        private readonly Security $security,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function createFromRequest(Request $request, bool $normalization, ?array $extractedAttributes = null): array
    {
        $context = $this->decorated->createFromRequest($request, $normalization, $extractedAttributes);

        // Only modify normalization contexts (responses)
        if (!$normalization) {
            return $context;
        }

        $resourceClass = $context['resource_class'] ?? null;

        // For Evento items, allow returning a more generic group for admin forms so
        // the frontend receives all fields needed to populate the create/edit form.
        if ($resourceClass === Evento::class) {
            $isAdmin = $this->security->isGranted('ROLE_ADMIN_ENTIDAD') || $this->security->isGranted('ROLE_SUPERADMIN');
            $formFlag = $request->query->get('form') === 'admin' || $request->query->get('form') === 'full';

            if ($isAdmin || $formFlag) {
                $groups = $context['groups'] ?? [];
                if (is_string($groups)) {
                    $groups = [$groups];
                }

                $groupsToAdd = ['evento:write', 'actividad-evento:write'];

                $context['groups'] = array_values(array_unique(array_merge($groups, $groupsToAdd)));
            }
        }

        return $context;
    }
}

