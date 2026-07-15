<?php

declare(strict_types=1);

namespace ChristianBrown\GetSmartHomeTemps;

final class DeviceReading implements DeviceReadingInterface
{
    private ?int $batteryValue;
    private ?bool $humidityStale;
    private ?int $humidityTimestamp;
    private ?float $humidityValue;
    private string $name;
    private ?string $roomName;
    private ?bool $temperatureStale;
    private ?int $temperatureTimestamp;
    private ?float $temperatureValue;

    public function __construct(string $name, ?string $roomName, ?int $batteryValue, ?float $temperatureValue, ?int $temperatureTimestamp, ?bool $temperatureStale, ?float $humidityValue, ?int $humidityTimestamp, ?bool $humidityStale)
    {
        $this->name = $name;
        $this->roomName = $roomName;
        $this->batteryValue = $batteryValue;
        $this->temperatureValue = $temperatureValue;
        $this->temperatureTimestamp = $temperatureTimestamp;
        $this->temperatureStale = $temperatureStale;
        $this->humidityValue = $humidityValue;
        $this->humidityTimestamp = $humidityTimestamp;
        $this->humidityStale = $humidityStale;
    }

    public function getBatteryValue(): ?int
    {
        return $this->batteryValue;
    }

    public function getHumidityTimestamp(): ?int
    {
        return $this->humidityTimestamp;
    }

    public function getHumidityValue(): ?float
    {
        return $this->humidityValue;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getRoomName(): ?string
    {
        return $this->roomName;
    }

    public function getTemperatureTimestamp(): ?int
    {
        return $this->temperatureTimestamp;
    }

    public function getTemperatureValue(): ?float
    {
        return $this->temperatureValue;
    }

    public function isHumidityStale(): ?bool
    {
        return $this->humidityStale;
    }

    public function isTemperatureStale(): ?bool
    {
        return $this->temperatureStale;
    }
}
