<?php

class WeightCalculatorService
{
    public function volumetricWeight($length, $width, $height, $weightVol)
    {
        if ($length <= 0 || $width <= 0 || $height <= 0) {
            return 0;
        }
        
        if ($weightVol <= 0) {
            return 0;
        }

        return ($length/100 * $width/100 * $height/100) * (float)$weightVol;
    }

    public function billableWeight($weightReal, $weightVol)
    {
        return max($weightReal, $weightVol);
    }
}