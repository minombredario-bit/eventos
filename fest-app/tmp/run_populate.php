<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

// Boot the Symfony kernel and retrieve the entity manager
$kernel = new \App\Kernel('prod', false);
$kernel->boot();
$container = $kernel->getContainer();
$doctrine = $container->get('doctrine');
$em = $doctrine->getManager();

// Prepare input/output for the command and run it directly
$input = new \Symfony\Component\Console\Input\StringInput('');
$output = new \Symfony\Component\Console\Output\ConsoleOutput();

$cmd = new \App\Command\PopulateUsuariosCommand($em);
$exit = $cmd->run($input, $output);
exit($exit);

