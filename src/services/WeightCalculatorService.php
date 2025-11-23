<?php

class WeightCalculatorService
{
    public function volumetricWeight($length, $width, $height)
    {
        if ($length <= 0 || $width <= 0 || $height <= 0) {
            return 0;
        }
        // cm³ / 5000 = kg volumétrico estándar
        return ($length * $width * $height) / 5000;
    }

    public function billableWeight($weightReal, $weightVol)
    {
        return max($weightReal, $weightVol);
    }
}