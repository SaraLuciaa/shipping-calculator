<?php

class WeightCalculatorService
{
    public function volumetricWeight($length, $width, $height, $weightVol)
    {
        if ($length <= 0 || $width <= 0 || $height <= 0) {
            return 0;
        }

        // Las dimensiones vienen en centímetros y el divisor (weightVol)
        // típicamente es algo como 5000 (cm^3 por kg). La fórmula correcta
        // es (L * W * H) / divisor para obtener kg.
        if ($weightVol <= 0) {
            return 0;
        }

        return ($length * $width * $height) / (float)$weightVol;
    }

    public function billableWeight($weightReal, $weightVol)
    {
        return max($weightReal, $weightVol);
    }
}