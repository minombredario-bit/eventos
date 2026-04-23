<?php

namespace App\Command;

use App\Entity\Entidad;
use App\Entity\Usuario;
use App\Enum\CensadoViaEnum;
use App\Enum\EstadoValidacionEnum;
use App\Enum\TipoEntidadEnum;
use App\Entity\TipoEntidad as TipoEntidadEntity;
use App\Enum\TipoPersonaEnum;
use App\Enum\TipoRelacionEconomicaEnum;
use App\Repository\EntidadRepository;
use App\Repository\UsuarioRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:ensure-superadmin',
    description: 'Crea el usuario superadmin si no existe (usa SUPERADMIN_EMAIL y SUPERADMIN_PASSWORD del entorno).',
)]
class EnsureSuperadminCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UsuarioRepository $usuarioRepository,
        private readonly EntidadRepository $entidadRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $email    = (string) (getenv('SUPERADMIN_EMAIL')    ?: ($_ENV['SUPERADMIN_EMAIL']    ?? ''));
        $password = (string) (getenv('SUPERADMIN_PASSWORD') ?: ($_ENV['SUPERADMIN_PASSWORD'] ?? ''));

        if ($email === '' || $password === '') {
            $io->warning('SUPERADMIN_EMAIL o SUPERADMIN_PASSWORD no configuradas. Omitiendo.');
            return Command::SUCCESS;
        }

        // ¿Ya existe algún usuario con ROLE_SUPERADMIN?
        $existente = $this->entityManager
            ->createQueryBuilder()
            ->select('u')
            ->from(Usuario::class, 'u')
            ->where('u.roles LIKE :role')
            ->setParameter('role', '%ROLE_SUPERADMIN%')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if ($existente !== null) {
            $io->note(sprintf('Ya existe un superadmin (%s). No se crea ninguno nuevo.', $existente->getEmail()));
            return Command::SUCCESS;
        }

        // ── Buscar o crear entidad "sistema" para anclar el superadmin ────────
        $entidad = $this->entidadRepository->findOneBy(['slug' => 'sistema']);

        if ($entidad === null) {
            // Intentar usar la primera entidad disponible
            $entidad = $this->entidadRepository->findOneBy([], ['id' => 'ASC']);
        }

        if ($entidad === null) {
            // Crear una entidad de sistema mínima
            $entidad = new Entidad();
            $entidad->setNombre('Sistema');
            $entidad->setSlug('sistema');
            // Map enum to TipoEntidad entity
            $tipoRepo = $this->entityManager->getRepository(TipoEntidadEntity::class);
            $tipo = $tipoRepo->findOneBy(['codigo' => TipoEntidadEnum::OTRO->value]);
            if ($tipo) {
                $entidad->setTipoEntidad($tipo);
            }
            $entidad->setTerminologiaSocio('usuario');
            $entidad->setTerminologiaEvento('evento');
            $entidad->setEmailContacto($email);
            $entidad->setCodigoRegistro('SISTEMA-' . substr(sha1(random_bytes(8)), 0, 8));
            $entidad->setTemporadaActual((string) date('Y'));
            $entidad->setActiva(true);
            $this->entityManager->persist($entidad);
            $io->note('Entidad "sistema" creada como soporte del superadmin.');
        }

        // ── Crear el superadmin ───────────────────────────────────────────────
        $superadmin = new Usuario();
        $superadmin->setEntidad($entidad);
        $superadmin->setNombre('Super');
        $superadmin->setApellidos('Admin');
        $superadmin->setEmail(strtolower(trim($email)));
        $superadmin->setPassword($this->passwordHasher->hashPassword($superadmin, $password));
        $superadmin->setRoles(['ROLE_SUPERADMIN']);
        $superadmin->setActivo(true);
        $superadmin->setCensadoVia(CensadoViaEnum::MANUAL);
        $superadmin->setTipoPersona(TipoPersonaEnum::ADULTO);

        $this->entityManager->persist($superadmin);
        $this->entityManager->flush();

        $io->success(sprintf('Superadmin creado correctamente: %s', $email));

        return Command::SUCCESS;
    }
}

