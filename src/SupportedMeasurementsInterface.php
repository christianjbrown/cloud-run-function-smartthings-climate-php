<?php

declare(strict_types=1);

namespace ChristianBrown\SmartThingsClimate;

interface SupportedMeasurementsInterface
{
    public function hasAny(): bool;

    public function supportsHumidity(): bool;

    public function supportsTemperature(): bool;
}
