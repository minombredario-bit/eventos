<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Usuario;
use App\Entity\RelacionUsuario;
use App\Enum\TipoRelacionEnum;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class PopulateRelacionUsuarioCommand extends Command
{
    protected static $defaultName = 'app:populate-relacion-usuario';
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct();
        $this->em = $em;
    }

    protected function configure(): void
    {
        $this->setDescription('Populate RelacionUsuario with 3 sample relations between the first 3 usuarios.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Fetch the first 3 usuarios (ascending by id)
        $usuarios = $this->em->getRepository(Usuario::class)->findBy([], ['id' => 'ASC'], 3);

        if (count($usuarios) < 3) {
            $output->writeln('<error>Not enough usuarios found. Found: ' . count($usuarios) . '</error>');
            return Command::FAILURE;
        }

        /** @var Usuario $u1 */
        $u1 = $usuarios[0];
        /** @var Usuario $u2 */
        $u2 = $usuarios[1];
        /** @var Usuario $u3 */
        $u3 = $usuarios[2];

        // Define a small set of relationships
        $pairs = [
            [$u1, $u2, TipoRelacionEnum::PAREJA],   // u1 -> u2 como pareja
            [$u1, $u3, TipoRelacionEnum::SOBRINO],   // u1 -> u3 como sobrino
            [$u2, $u3, TipoRelacionEnum::SOBRINA],  // u2 -> u3 como sobrina
        ];

        $inserted = 0;
        foreach ($pairs as $p) {
            [$a, $b, $tipo] = $p;
            // Check for existing relation in this direction
            $exists = $this->em->getRepository(RelacionUsuario::class)->findOneBy([
                'usuarioOrigen' => $a,
                'usuarioDestino' => $b,
            ]);
            if ($exists) {
                $output->writeln("<comment>Existing relation found for {$a->getId()} -> {$b->getId()} ; skipping.</comment>");
                continue;
            }

            $rel = new RelacionUsuario();
            $rel->setUsuarioOrigen($a)
                ->setUsuarioDestino($b)
                ->setTipoRelacion($tipo);

            $this->em->persist($rel);
            $inserted++;
        }

        $this->em->flush();
        $output->writeln("<info>Inserted {$inserted} RelacionUsuario record(s).</info>");

        return Command::SUCCESS;
    }
}
