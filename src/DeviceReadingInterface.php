<?php

declare(strict_types=1);

namespace ChristianBrown\SmartThingsClimate;

interface DeviceReadingInterface
{
    public function getHumidity(): ?MeasurementInterface;

    public function getName(): string;

    public function getRoomName(): ?string;

    public function getTemperature(): ?MeasurementInterface;
}
