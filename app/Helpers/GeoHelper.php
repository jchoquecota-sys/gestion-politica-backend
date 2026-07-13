<?php

namespace App\Helpers;

class GeoHelper
{
    /**
     * Calcula la distancia en metros entre dos coordenadas GPS utilizando la fórmula de Haversine.
     *
     * @param float $lat1 Latitud del punto origen
     * @param float $lon1 Longitud del punto origen
     * @param float $lat2 Latitud del punto destino
     * @param float $lon2 Longitud del punto destino
     * @return float Distancia en metros
     */
    public static function calcularDistancia(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371000; // Radio de la tierra en metros

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }
}
