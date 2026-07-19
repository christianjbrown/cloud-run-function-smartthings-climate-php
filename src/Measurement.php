<?php

declare(strict_types=1);

namespace ChristianBrown\SmartThingsClimate;

final class Measurement implements MeasurementInterface
{
    private ?bool $stale;
    private ?int $timestamp;
    private ?float $value;

    public function __construct(?float $value, ?int $timestamp, ?bool $stale)
    {
        $this->stale = $stale;
        $this->timestamp = $timestamp;
        $this->value = $value;
    }

    public function getTimestamp(): ?int
    {
        return $this->timestamp;
    }

    public function getValue(): ?float
    {
        return $this->value;
    }

    public function isStale(): ?bool
    {
        return $this->stale;
    }
}
