<?php
namespace App\Tests\Functional;

use ApiPlatform\Metadata\Post;
use App\Entity\RelacionUsuario;
use App\Entity\Usuario;
use App\Enum\TipoRelacionEnum;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

final class RelacionUsuarioInverseTest extends KernelTestCase
{
    public function test_creates_inverse_relation_when_posting_relation(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $em = $container->get('doctrine')->getManager();

        // Create entidad placeholder if necessary via Usuario->entidad reuse existing fixtures
        $entidadRepo = $em->getRepository(\App\Entity\Entidad::class);
        $entidad = $entidadRepo->findOneBy([]);
        if (!$entidad) {
            $this->markTestSkipped('No Entidad found to attach users. Load fixtures first.');
        }

        // Create two users
        $u1 = new Usuario();
        $u1->setNombre('Origen');
        $u1->setApellidos('Test');
        $u1->setEmail('origentest@example.local');
        $u1->setPassword('pass');
        $u1->setEntidad($entidad);
        $u1->setTipoPersona(\App\Enum\TipoPersonaEnum::ADULTO);

        $u2 = new Usuario();
        $u2->setNombre('Destino');
        $u2->setApellidos('Test');
        $u2->setEmail('destinotest@example.local');
        $u2->setPassword('pass');
        $u2->setEntidad($entidad);
        $u2->setTipoPersona(\App\Enum\TipoPersonaEnum::ADULTO);

        $em->persist($u1);
        $em->persist($u2);
        $em->flush();

        // Make current user a superadmin so processor allows creation
        $admin = new Usuario();
        $admin->setNombre('Admin');
        $admin->setApellidos('Test');
        $admin->setEmail('admintest@example.local');
        $admin->setPassword('pass');
        $admin->setEntidad($entidad);
        $admin->setTipoPersona(\App\Enum\TipoPersonaEnum::ADULTO);
        $admin->setRoles(['ROLE_SUPERADMIN']);
        $em->persist($admin);
        $em->flush();

        // Set token
        $token = new UsernamePasswordToken($admin, 'none', 'main', (array) $admin->getRoles());
        $container->get('security.token_storage')->setToken($token);

        $processor = $container->get(\App\State\RelacionUsuarioProcessor::class);

        $rel = new RelacionUsuario();
        $rel->setUsuarioDestino($u2);
        $rel->setTipoRelacion(TipoRelacionEnum::PADRE);

        $uriVariables = ['id' => $u1->getId()];

        $saved = $processor->process($rel, new Post(), $uriVariables);

        $this->assertNotNull($saved->getId());

        // Check that inverse exists: attribute PADRE -> inverse should be HIJO on the other side
        $repo = $em->getRepository(RelacionUsuario::class);
        $inverse = $repo->findOneBy(['usuarioOrigen' => $u2, 'usuarioDestino' => $u1, 'tipoRelacion' => TipoRelacionEnum::HIJO]);

        $this->assertNotNull($inverse, 'Expected inverse relation (HIJO) created.');
    }
}

