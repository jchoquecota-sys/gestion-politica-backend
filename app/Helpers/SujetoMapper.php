<?php

namespace App\Helpers;

use App\Models\Persona;
use App\Models\Base;
use App\Models\Sector;
use InvalidArgumentException;

class SujetoMapper
{
    /**
     * Mapea un string descriptivo de tipo de sujeto a su correspondiente nombre de clase Eloquent.
     *
     * @param string $type Tipo de sujeto ('persona', 'base', 'sector')
     * @return string Nombre de la clase Eloquent correspondiente
     * @throws InvalidArgumentException Si el tipo no es válido
     */
    public static function map(string $type): string
    {
        return match (strtolower($type)) {
            'persona' => Persona::class,
            'base' => Base::class,
            'sector' => Sector::class,
            default => throw new InvalidArgumentException("Tipo de sujeto no válido: {$type}"),
        };
    }
}
