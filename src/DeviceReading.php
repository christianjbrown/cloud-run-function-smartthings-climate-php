<?php

declare(strict_types=1);

namespace ChristianBrown\GetSmartHomeTemps;

final class DeviceReading implements DeviceReadingInterface
{
    private ?float $humidity;
    private ?bool $humidityStale;
    private ?int $humidityTimestamp;
    private string $label;
    private ?bool $stale;
    private ?float $temperature;
    private ?int $timestamp;

    public function __construct(string $label, ?float $temperature, ?int $timestamp, ?bool $stale, ?float $humidity, ?int $humidityTimestamp, ?bool $humidityStale)
    {
        $this->label = $label;
        $this->temperature = $temperature;
        $this->timestamp = $timestamp;
        $this->stale = $stale;
        $this->humidity = $humidity;
        $this->humidityTimestamp = $humidityTimestamp;
        $this->humidityStale = $humidityStale;
    }

    public function getHumidity(): ?float
    {
        return $this->humidity;
    }

    public function getHumidityTimestamp(): ?int
    {
        return $this->humidityTimestamp;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getTemperature(): ?float
    {
        return $this->temperature;
    }

    public function getTimestamp(): ?int
    {
        return $this->timestamp;
    }

    public function isHumidityStale(): ?bool
    {
        return $this->humidityStale;
    }

    public function isStale(): ?bool
    {
        return $this->stale;
    }
}
