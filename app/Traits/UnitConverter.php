<?php

namespace App\Traits;

use InvalidArgumentException;

trait UnitConverter
{
    /**
     * Convert centimeters to feet.
     *
     * @param float|int $cm
     * @return float
     * @throws InvalidArgumentException
     */
    public function cmToFeet(float|int $cm): float
    {
        if (!is_numeric($cm)) {
            throw new InvalidArgumentException("Invalid input: must be a number");
        }
        return (float) $cm / 30.48;
    }

    /**
     * Convert feet to centimeters.
     *
     * @param float|int $feet
     * @return float
     * @throws InvalidArgumentException
     */
    public function feetToCm(float|int $feet): float
    {
        if (!is_numeric($feet)) {
            throw new InvalidArgumentException("Invalid input: must be a number");
        }
        return (float) $feet * 30.48;
    }

    /**
     * Convert kilograms to pounds.
     *
     * @param float|int $kg
     * @return float
     * @throws InvalidArgumentException
     */
    public function kgToLbs(float|int $kg): float
    {
        if (!is_numeric($kg)) {
            throw new InvalidArgumentException("Invalid input: must be a number");
        }
        return (float) $kg * 2.20462;
    }

    /**
     * Convert pounds to kilograms.
     *
     * @param float|int $lbs
     * @return float
     * @throws InvalidArgumentException
     */
    public function lbsToKg(float|int $lbs): float
    {
        if (!is_numeric($lbs)) {
            throw new InvalidArgumentException("Invalid input: must be a number");
        }
        return (float) $lbs / 2.20462;
    }
}
