<?php

namespace App\Dto;

use Symfony\Component\Serializer\Annotation\Groups;

final class ApuntadosPageOutput
{
    public function __construct(
        #[Groups(['apuntado:read'])]
        public readonly array $evento,

        #[Groups(['apuntado:read'])]
        public readonly array $member,

        #[Groups(['apuntado:read'])]
        public readonly int $totalItems,

        #[Groups(['apuntado:read'])]
        public readonly int $itemsPerPage,

        #[Groups(['apuntado:read'])]
        public readonly int $currentPage,

        #[Groups(['apuntado:read'])]
        public readonly int $lastPage,
    ) {
    }
}
