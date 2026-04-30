<?php

namespace App\Dto;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Put;
use App\Entity\Evento;
use App\State\SeleccionParticipantesEventoDeleteProcessor;
use App\State\SeleccionParticipantesEventoProvider;
use App\State\SeleccionParticipantesEventoPutProcessor;
use Symfony\Component\Serializer\Annotation\Groups;

#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/eventos/{eventoId}/seleccion_participantes',
            uriVariables: [
                'eventoId' => new Link(fromClass: Evento::class, identifiers: ['id']),
            ],
            normalizationContext: ['groups' => ['seleccion_participantes_evento_endpoint:read']],
            security: "is_granted('ROLE_USER')",
            provider: SeleccionParticipantesEventoProvider::class
        ),
        new Put(
            uriTemplate: '/eventos/{eventoId}/seleccion_participantes',
            uriVariables: [
                'eventoId' => new Link(fromClass: Evento::class, identifiers: ['id']),
            ],
            normalizationContext: ['groups' => ['seleccion_participantes_evento_endpoint:read']],
            denormalizationContext: ['groups' => ['seleccion_participantes_evento_endpoint:write']],
            security: "is_granted('ROLE_USER')",
            input: SeleccionParticipantesInput::class,
            output: self::class,
            read: false,
            processor: SeleccionParticipantesEventoPutProcessor::class
        ),
        new Delete(
            uriTemplate: '/eventos/{eventoId}/seleccion_participantes',
            uriVariables: [
                'eventoId' => new Link(fromClass: Evento::class, identifiers: ['id']),
            ],
            status: 204,
            security: "is_granted('ROLE_USER')",
            output: false,
            read: false,
            processor: SeleccionParticipantesEventoDeleteProcessor::class
        ),
    ]
)]
class SeleccionParticipantesView
{
    #[Groups(['seleccion_participantes_evento_endpoint:read'])]
    public ?string $eventoId = null;

    /**
     * @var list<array<string, mixed>>
     */
    #[Groups(['seleccion_participantes_evento_endpoint:read'])]
    public array $participantes = [];

    #[Groups(['seleccion_participantes_evento_endpoint:read'])]
    public ?string $updatedAt = null;

    /**
     * Inscripciones encontradas para los participantes del evento (se incluye para evitar llamadas adicionales desde
     * el frontend). Estructura análoga a la salida de /api/inscripcions.
     *
     * @var list<array<string, mixed>>
     */
    #[Groups(['seleccion_participantes_evento_endpoint:read'])]
    public array $inscripciones = [];
}
