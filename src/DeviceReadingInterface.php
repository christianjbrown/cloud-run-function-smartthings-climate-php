<?php

declare(strict_types=1);

namespace ChristianBrown\GetSmartHomeTemps;

interface DeviceReadingInterface
{
    public function getHumidity(): ?float;

    public function getHumidityTimestamp(): ?int;

    public function getLabel(): string;

    public function getRoomName(): ?string;

    public function getTemperature(): ?float;

    public function getTimestamp(): ?int;

    public function isHumidityStale(): ?bool;

    public function isStale(): ?bool;
}
