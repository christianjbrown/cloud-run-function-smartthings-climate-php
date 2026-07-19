<?php

declare(strict_types=1);

namespace ChristianBrown\SmartThingsClimate;

interface MeasurementInterface
{
    public function getTimestamp(): ?int;

    public function getValue(): ?float;

    public function isStale(): ?bool;
}
