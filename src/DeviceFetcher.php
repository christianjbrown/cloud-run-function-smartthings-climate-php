<?php

declare(strict_types=1);

namespace ChristianBrown\SmartThingsClimate;

use ChristianBrown\SmartThings\Api\DeviceApiInterface;
use ChristianBrown\SmartThings\Model\DeviceInterface;

final class DeviceFetcher implements DeviceFetcherInterface
{
    private DeviceApiInterface $deviceApi;
    private string $locationId;

    public function __construct(DeviceApiInterface $deviceApi, string $locationId)
    {
        $this->deviceApi = $deviceApi;
        $this->locationId = $locationId;
    }

    /**
     * @return DeviceInterface[]
     */
    public function fetch(): array
    {
        return $this->deviceApi->getMultiple($this->locationId);
    }
}
