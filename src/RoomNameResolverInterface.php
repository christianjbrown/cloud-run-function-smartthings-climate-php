<?php

declare(strict_types=1);

namespace ChristianBrown\SmartThingsClimate;

use ChristianBrown\SmartThings\Model\DeviceInterface;

interface RoomNameResolverInterface
{
    /**
     * The name of the room the device is assigned to, or null when it has none.
     */
    public function resolve(DeviceInterface $device): ?string;
}
