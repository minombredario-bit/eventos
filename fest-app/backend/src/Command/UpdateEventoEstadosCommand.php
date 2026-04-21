<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Evento;
use App\Enum\EstadoEventoEnum;
use App\Repository\EventoRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:eventos:actualizar-estados',
    description: 'Actualiza automaticamente el estado de los eventos en funcion de sus fechas.'
)]
class UpdateEventoEstadosCommand extends Command
{
    public function __construct(
        private readonly EventoRepository $eventoRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Muestra cambios sin persistir en BD.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');
        $ahora = new \DateTimeImmutable();

        $eventos = $this->eventoRepository->findForEstadoAutomation();
        $cambios = 0;

        foreach ($eventos as $evento) {
            $estadoActual = $evento->getEstado();
            $estadoNuevo = $this->resolverEstado($evento, $ahora);

            if ($estadoActual === $estadoNuevo) {
                continue;
            }

            $cambios++;
            $io->writeln(sprintf(
                '%s [%s] %s -> %s',
                $evento->getTitulo(),
                (string) $evento->getId(),
                $estadoActual->value,
                $estadoNuevo->value
            ));

            if (!$dryRun) {
                $evento->setEstado($estadoNuevo);
            }
        }

        if (!$dryRun && $cambios > 0) {
            $this->entityManager->flush();
        }

        if ($dryRun) {
            $io->success(sprintf('Dry-run completado. Cambios detectados: %d', $cambios));
        } else {
            $io->success(sprintf('Actualizacion completada. Eventos actualizados: %d', $cambios));
        }

        return Command::SUCCESS;
    }

    private function resolverEstado(Evento $evento, \DateTimeImmutable $ahora): EstadoEventoEnum
    {
        $finEvento = $evento->getFechaEvento()->setTime(23, 59, 59, 999999);
        $finInscripcion = $evento->getFechaFinInscripcion();
        $inicioInscripcion = $evento->getFechaInicioInscripcion();

        if ($ahora > $finEvento) {
            return EstadoEventoEnum::FINALIZADO;
        }

        if ($finInscripcion !== null && $ahora > $finInscripcion->setTime(23, 59, 59, 999999)) {
            return EstadoEventoEnum::CERRADO;
        }

        if ($inicioInscripcion !== null && $ahora >= $inicioInscripcion) {
            return EstadoEventoEnum::PUBLICADO;
        }

        return EstadoEventoEnum::BORRADOR;
    }
}

