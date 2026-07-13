<?php

use App\Helpers\SujetoMapper;
use App\Models\Persona;
use App\Models\Base;
use App\Models\Sector;

test('it maps persona to Persona class name case insensitively', function () {
    expect(SujetoMapper::map('persona'))->toBe(Persona::class);
    expect(SujetoMapper::map('Persona'))->toBe(Persona::class);
    expect(SujetoMapper::map('PERSONA'))->toBe(Persona::class);
});

test('it maps base to Base class name case insensitively', function () {
    expect(SujetoMapper::map('base'))->toBe(Base::class);
    expect(SujetoMapper::map('Base'))->toBe(Base::class);
});

test('it maps sector to Sector class name case insensitively', function () {
    expect(SujetoMapper::map('sector'))->toBe(Sector::class);
    expect(SujetoMapper::map('SECTOR'))->toBe(Sector::class);
});

test('it throws InvalidArgumentException for invalid sujeto types', function () {
    SujetoMapper::map('invalid_type');
})->throws(InvalidArgumentException::class, 'Tipo de sujeto no válido: invalid_type');
