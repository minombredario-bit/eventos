<?php

namespace App\Command;

use App\Repository\UsuarioTemporadaCargoRepository;
use App\Service\TipoPersonaService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:usuarios:actualizar-tipo-persona',
    description: 'Actualiza tipo_persona según edad',
)]
class ActualizarTipoPersonaCommand extends Command
{
    public function __construct(
        private readonly UsuarioTemporadaCargoRepository $repository,
        private readonly TipoPersonaService $service,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'fecha',
            null,
            InputOption::VALUE_REQUIRED,
            'Fecha referencia YYYY-MM-DD'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $fecha = new \DateTimeImmutable();

        if ($input->getOption('fecha')) {
            $fecha = new \DateTimeImmutable((string) $input->getOption('fecha'));
        }

        $items = $this->repository->findAll();
        $cambios = 0;

        foreach ($items as $item) {
            $usuario = $item->getUsuario();

            if (!$usuario || !$usuario->getFechaNacimiento()) {
                continue;
            }

            $nuevo = $this->service->resolverPorEdad(
                $usuario->getFechaNacimiento(),
                $fecha
            );

            if ($item->getTipoPersona() !== $nuevo) {
                $item->setTipoPersona($nuevo);
                ++$cambios;
            }
        }

        $this->entityManager->flush();

        $io->success("Registros actualizados: {$cambios}");

        return Command::SUCCESS;
    }
}
