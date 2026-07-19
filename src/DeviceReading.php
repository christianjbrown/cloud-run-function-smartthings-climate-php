<?php

declare(strict_types=1);

namespace ChristianBrown\SmartThingsClimate;

final class DeviceReading implements DeviceReadingInterface
{
    private ?MeasurementInterface $humidity;
    private string $name;
    private ?string $roomName;
    private ?MeasurementInterface $temperature;

    public function __construct(string $name, ?string $roomName, ?MeasurementInterface $temperature, ?MeasurementInterface $humidity)
    {
        $this->humidity = $humidity;
        $this->name = $name;
        $this->roomName = $roomName;
        $this->temperature = $temperature;
    }

    public function getHumidity(): ?MeasurementInterface
    {
        return $this->humidity;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getRoomName(): ?string
    {
        return $this->roomName;
    }

    public function getTemperature(): ?MeasurementInterface
    {
        return $this->temperature;
    }
}
