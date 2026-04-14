<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$kernel = new \App\Kernel('prod', false);
$kernel->boot();
$container = $kernel->getContainer();
$doctrine = $container->get('doctrine');
$em = $doctrine->getManager();

$repo = $em->getRepository(\App\Entity\Usuario::class);
$count = method_exists($repo, 'count') ? $repo->count([]) : count($repo->findAll());
echo "usuario_count:" . $count . PHP_EOL;

// list emails for debugging
$users = $repo->findBy([], ['createdAt' => 'ASC']);
foreach ($users as $u) {
    echo $u->getEmail() . PHP_EOL;
}


