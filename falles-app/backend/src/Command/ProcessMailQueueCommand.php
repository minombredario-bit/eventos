<?php

namespace App\Command;

use App\Service\EmailQueueService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:mail-queue:process', description: 'Procesa la cola de correos pendientes.')]
class ProcessMailQueueCommand extends Command
{
    public function __construct(private readonly EmailQueueService $emailQueueService)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('limit', null, InputOption::VALUE_OPTIONAL, 'Número máximo de correos a procesar', 50);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $limit = (int) $input->getOption('limit');
        $count = $this->emailQueueService->processPending($limit);

        $io->success(sprintf('Correos procesados: %d', $count));

        return Command::SUCCESS;
    }
}

