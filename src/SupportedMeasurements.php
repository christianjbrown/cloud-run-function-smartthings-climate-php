<?php

declare(strict_types=1);

namespace ChristianBrown\SmartThingsClimate;

final class SupportedMeasurements implements SupportedMeasurementsInterface
{
    private bool $humidity;
    private bool $temperature;

    public function __construct(bool $temperature, bool $humidity)
    {
        $this->humidity = $humidity;
        $this->temperature = $temperature;
    }

    public function hasAny(): bool
    {
        return $this->temperature || $this->humidity;
    }

    public function supportsHumidity(): bool
    {
        return $this->humidity;
    }

    public function supportsTemperature(): bool
    {
        return $this->temperature;
    }
}
