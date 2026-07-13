<?php

use App\Helpers\GeoHelper;

test('it returns zero distance for identical coordinates', function () {
    $lat = -12.046374;
    $lon = -77.042793;

    $distancia = GeoHelper::calcularDistancia($lat, $lon, $lat, $lon);

    expect($distancia)->toBe(0.0);
});

test('it calculates correct distance for a short range (approx 98 meters)', function () {
    // Plaza de Armas de Lima a la Catedral de Lima
    $lat1 = -12.046374;
    $lon1 = -77.042793;
    $lat2 = -12.047020;
    $lon2 = -77.042140;

    $distancia = GeoHelper::calcularDistancia($lat1, $lon1, $lat2, $lon2);

    // Debe estar aproximadamente en 98 metros (margen de error de 5 metros por aproximaciones de la esfera)
    expect($distancia)->toBeGreaterThan(90)
        ->toBeLessThan(110);
});

test('it calculates correct distance for a long range (Lima to Cusco, approx 570km)', function () {
    $lat1 = -12.046374;
    $lon1 = -77.042793;
    $lat2 = -13.531944;
    $lon2 = -71.9675;

    $distancia = GeoHelper::calcularDistancia($lat1, $lon1, $lat2, $lon2);

    // Aproximadamente 570 - 580 kilómetros
    expect($distancia)->toBeGreaterThan(570000)
        ->toBeLessThan(585000);
});
