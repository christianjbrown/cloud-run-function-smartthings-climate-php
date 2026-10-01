<?php

declare(strict_types=1);

namespace ChristianBrown\SmartThingsClimate;

use ChristianBrown\SmartThings\Model\DeviceInterface;

interface DeviceFetcherInterface
{
    /**
     * @return DeviceInterface[]
     */
    public function fetch(): array;
}
