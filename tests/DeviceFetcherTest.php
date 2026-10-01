<?php

declare(strict_types=1);

namespace ChristianBrown\SmartThingsClimate\Tests;

use ChristianBrown\SmartThings\Api\DeviceApiInterface;
use ChristianBrown\SmartThings\Model\DeviceInterface;
use ChristianBrown\SmartThingsClimate\DeviceFetcher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\TestCase;

#[CoversClass(DeviceFetcher::class)]
final class DeviceFetcherTest extends TestCase
{
    /**
     * @throws Exception
     */
    public function test(): void
    {
        $devices = [self::createStub(DeviceInterface::class)];

        $deviceApi = self::createMock(DeviceApiInterface::class);
        $deviceApi->expects(self::once())
            ->method('getMultiple')
            ->with('test-location-id')
            ->willReturn($devices);

        self::assertSame($devices, (new DeviceFetcher($deviceApi, 'test-location-id'))->fetch());
    }
}
