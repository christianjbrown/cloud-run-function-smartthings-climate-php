<?php

declare(strict_types=1);

namespace ChristianBrown\SmartThingsClimate;

use ChristianBrown\SmartThings\Api\LocationRoomApiInterface;
use ChristianBrown\SmartThings\Model\DeviceInterface;
use ChristianBrown\SmartThings\Model\LocationInterface;
use ChristianBrown\SmartThings\Model\LocationRoomInterface;

use function array_combine;
use function array_map;

final class RoomNameResolver implements RoomNameResolverInterface
{
    private LocationInterface $location;
    private LocationRoomApiInterface $locationRoomApi;

    public function __construct(LocationRoomApiInterface $locationRoomApi, LocationInterface $location)
    {
        $this->location = $location;
        $this->locationRoomApi = $locationRoomApi;
    }

    public function resolve(DeviceInterface $device): ?string
    {
        $roomId = $device->getRoomId();
        if (null === $roomId) {
            return null;
        }

        // One list call for the whole location rather than a lookup per room:
        // the SDK caches the list, so every device after the first is answered
        // without another request against the SmartThings monthly call quota.
        $rooms = $this->locationRoomApi->getMultiple($this->location);
        $names = array_combine(
            array_map(static fn (LocationRoomInterface $room): string => $room->getRoomId(), $rooms),
            array_map(static fn (LocationRoomInterface $room): ?string => $room->getName(), $rooms)
        );

        return $names[$roomId] ?? null;
    }
}
