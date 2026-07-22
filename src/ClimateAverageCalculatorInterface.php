<?php

declare(strict_types=1);

namespace ChristianBrown\SmartThingsClimate;

interface ClimateAverageCalculatorInterface
{
    /**
     * @param DeviceReadingInterface[] $deviceReadings
     */
    public function averageHumidity(array $deviceReadings): ?float;

    /**
     * @param DeviceReadingInterface[] $deviceReadings
     */
    public function averageTemperature(array $deviceReadings): ?float;
}
