<?php

namespace App\Tests\Unit\State;

use ApiPlatform\Metadata\Operation;
use App\Entity\Invitado;
use App\Repository\InvitadoRepository;
use App\State\InvitadoProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class InvitadoProviderTest extends TestCase
{
    public function testProvideCollectionUsesOnlyActiveRepositoryQuery(): void
    {
        $invitado = new Invitado();

        $repository = $this->createMock(InvitadoRepository::class);
        $repository
            ->expects($this->once())
            ->method('findActiveAllOrderedByCreatedAtDesc')
            ->willReturn([$invitado]);

        $provider = new InvitadoProvider($repository);

        $result = $provider->provide($this->createMock(Operation::class));

        $this->assertSame([$invitado], $result);
    }

    public function testProvideItemThrowsNotFoundWhenInvitadoIsLogicallyDeletedOrMissing(): void
    {
        $repository = $this->createMock(InvitadoRepository::class);
        $repository
            ->expects($this->once())
            ->method('findActiveById')
            ->with('nf-1')
            ->willReturn(null);

        $provider = new InvitadoProvider($repository);

        $this->expectException(NotFoundHttpException::class);
        $provider->provide($this->createMock(Operation::class), ['id' => 'nf-1']);
    }
}
