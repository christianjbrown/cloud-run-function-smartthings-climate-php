<?php

declare(strict_types=1);

namespace ChristianBrown\SmartThingsClimate;

interface DeviceReadingInterface
{
    public function getHumidityTimestamp(): ?int;

    public function getHumidityValue(): ?float;

    public function getName(): string;

    public function getRoomName(): ?string;

    public function getTemperatureTimestamp(): ?int;

    public function getTemperatureValue(): ?float;

    public function isHumidityStale(): ?bool;

    public function isTemperatureStale(): ?bool;
}
