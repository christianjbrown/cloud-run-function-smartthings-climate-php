<?php

declare(strict_types=1);

namespace ChristianBrown\SmartThingsClimate;

interface OutputTransformerInterface
{
    public const KEY_DEVICES = 'devices';

    /**
     * @param DeviceReadingInterface[] $deviceTemperatures
     *
     * @return mixed[]
     */
    public function transform(array $deviceTemperatures): array;
}
