<?php

declare(strict_types=1);

namespace ChristianBrown\SmartThingsClimate\Tests;

use ChristianBrown\SmartThings\Api\LocationRoomApiInterface;
use ChristianBrown\SmartThings\Model\DeviceInterface;
use ChristianBrown\SmartThings\Model\LocationInterface;
use ChristianBrown\SmartThings\Model\LocationRoomInterface;
use ChristianBrown\SmartThingsClimate\RoomNameResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\TestCase;

#[CoversClass(RoomNameResolver::class)]
final class RoomNameResolverTest extends TestCase
{
    /**
     * @throws Exception
     */
    public function testDeviceWithoutARoomMakesNoRequest(): void
    {
        $locationRoomApi = $this->createMock(LocationRoomApiInterface::class);
        $locationRoomApi->expects($this->never())
            ->method('getMultiple');

        $resolver = new RoomNameResolver($locationRoomApi, self::createStub(LocationInterface::class));

        self::assertNull($resolver->resolve($this->createDevice(null)));
    }

    /**
     * @throws Exception
     */
    public function testEmptyRoomListResolvesToNull(): void
    {
        $locationRoomApi = self::createStub(LocationRoomApiInterface::class);
        $locationRoomApi->method('getMultiple')
            ->willReturn([]);

        $resolver = new RoomNameResolver($locationRoomApi, self::createStub(LocationInterface::class));

        self::assertNull($resolver->resolve($this->createDevice('test-room-id-a')));
    }

    /**
     * @throws Exception
     */
    public function testRoomIsFoundInTheLocationsRoomList(): void
    {
        $location = self::createStub(LocationInterface::class);
        $locationRoomApi = $this->createMock(LocationRoomApiInterface::class);
        $locationRoomApi->expects($this->once())
            ->method('getMultiple')
            ->with($location)
            ->willReturn([
                $this->createRoom('test-room-id-a', 'test-room-a'),
                $this->createRoom('test-room-id-b', 'test-room-b'),
            ]);

        $resolver = new RoomNameResolver($locationRoomApi, $location);

        self::assertSame('test-room-b', $resolver->resolve($this->createDevice('test-room-id-b')));
    }

    /**
     * @throws Exception
     */
    public function testRoomMissingFromTheListResolvesToNull(): void
    {
        $locationRoomApi = self::createStub(LocationRoomApiInterface::class);
        $locationRoomApi->method('getMultiple')
            ->willReturn([$this->createRoom('test-room-id-a', 'test-room-a')]);

        $resolver = new RoomNameResolver($locationRoomApi, self::createStub(LocationInterface::class));

        self::assertNull($resolver->resolve($this->createDevice('test-room-id-missing')));
    }

    /**
     * @throws Exception
     */
    private function createDevice(?string $roomId): DeviceInterface
    {
        $device = self::createStub(DeviceInterface::class);
        $device->method('getRoomId')
            ->willReturn($roomId);

        return $device;
    }

    /**
     * @throws Exception
     */
    private function createRoom(string $roomId, string $name): LocationRoomInterface
    {
        $room = self::createStub(LocationRoomInterface::class);
        $room->method('getRoomId')
            ->willReturn($roomId);
        $room->method('getName')
            ->willReturn($name);

        return $room;
    }
}
