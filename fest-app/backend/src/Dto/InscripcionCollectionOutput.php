<?php

namespace App\Dto;

use ApiPlatform\Metadata\ApiProperty;

final class InscripcionCollectionOutput implements \JsonSerializable
{
    #[ApiProperty(readable: true)]
    public string $id;

    #[ApiProperty(readable: true)]
    public string $codigo;

    #[ApiProperty(readable: true)]
    /** @var array<string, mixed> */
    public array $evento = [];

    #[ApiProperty(readable: true)]
    public string $estadoInscripcion;

    #[ApiProperty(readable: true)]
    public string $estadoPago;

    #[ApiProperty(readable: true)]
    public float $importeTotal = 0.0;

    #[ApiProperty(readable: true)]
    public float $importePagado = 0.0;

    #[ApiProperty(readable: true)]
    public string $moneda = 'EUR';

    #[ApiProperty(readable: true)]
    public int $totalLineas = 0;

    #[ApiProperty(readable: true)]
    /** @var list<array<string, mixed>> */
    public array $lineas = [];

    public function jsonSerialize(): mixed
    {
        return [
            'id' => $this->id,
            'codigo' => $this->codigo,
            'evento' => $this->evento,
            'estadoInscripcion' => $this->estadoInscripcion,
            'estadoPago' => $this->estadoPago,
            'importeTotal' => $this->importeTotal,
            'importePagado' => $this->importePagado,
            'moneda' => $this->moneda,
            'totalLineas' => $this->totalLineas,
            'lineas' => $this->lineas,
        ];
    }
}

