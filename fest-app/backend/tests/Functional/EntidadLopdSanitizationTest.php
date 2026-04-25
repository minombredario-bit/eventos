<?php
namespace App\Tests\Functional;

use App\Entity\Entidad;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class EntidadLopdSanitizationTest extends KernelTestCase
{
	public function test_texto_lopd_is_sanitized_when_htmlpurifier_available(): void
	{
		if (!class_exists('\HTMLPurifier')) {
			$this->markTestSkipped('HTMLPurifier not installed. Run: composer require ezyang/htmlpurifier');
		}

		self::bootKernel();
		$container = static::getContainer();
		$em = $container->get('doctrine')->getManager();

		$entidad = new Entidad();
		$entidad->setNombre('Test Entidad');
		$entidad->setSlug('test-entidad-sanitizer');
		$entidad->setCodigoRegistro('TEST-001');
		$entidad->setTemporadaActual('2026');
		$entidad->setEmailContacto('contact@test.local');

		$dirty = '<p>Hola</p><script>alert(1)</script><img src="x" onerror="alert(2)" />';
		$entidad->setTextoLopd($dirty);

		$em->persist($entidad);
		$em->flush();

		$em->refresh($entidad);
		$stored = $entidad->getTextoLopd();

		$this->assertStringNotContainsString('<script', $stored);
		$this->assertStringContainsString('<p>Hola</p>', $stored);
		$this->assertStringNotContainsString('onerror', $stored);
	}
}


