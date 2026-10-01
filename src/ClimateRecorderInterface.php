<?php

declare(strict_types=1);

namespace ChristianBrown\SmartThingsClimate;

interface ClimateRecorderInterface
{
    /**
     * @param DeviceReadingInterface[] $readings
     */
    public function record(array $readings): void;
}
