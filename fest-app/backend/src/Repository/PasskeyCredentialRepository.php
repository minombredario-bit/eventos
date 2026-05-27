<?php

namespace App\Repository;

use App\Entity\PasskeyCredential;
use App\Entity\Usuario;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PasskeyCredential>
 */
class PasskeyCredentialRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PasskeyCredential::class);
    }

    /** @return PasskeyCredential[] */
    public function findByUsuario(Usuario $usuario): array
    {
        return $this->findBy(['usuario' => $usuario], ['createdAt' => 'DESC']);
    }

    public function findByCredentialId(string $credentialId): ?PasskeyCredential
    {
        return $this->findOneBy(['credentialId' => $credentialId]);
    }
}

