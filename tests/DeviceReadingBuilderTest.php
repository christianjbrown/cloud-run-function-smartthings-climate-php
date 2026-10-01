<?php

declare(strict_types=1);

namespace ChristianBrown\SmartThingsClimate\Tests;

use ChristianBrown\SmartThings\Api\DeviceStatusApiInterface;
use ChristianBrown\SmartThings\Api\LocationRoomApiInterface;
use ChristianBrown\SmartThings\Model\DeviceInterface;
use ChristianBrown\SmartThings\Model\DeviceStatusInterface;
use ChristianBrown\SmartThings\Model\DeviceStatusRelativeHumidityMeasurementHumidityInterface;
use ChristianBrown\SmartThings\Model\DeviceStatusRelativeHumidityMeasurementInterface;
use ChristianBrown\SmartThings\Model\DeviceStatusTemperatureMeasurementInterface;
use ChristianBrown\SmartThings\Model\DeviceStatusTemperatureMeasurementTemperatureInterface;
use ChristianBrown\SmartThings\Model\LocationRoomInterface;
use ChristianBrown\SmartThingsClimate\DeviceReading;
use ChristianBrown\SmartThingsClimate\DeviceReadingBuilder;
use ChristianBrown\SmartThingsClimate\Measurement;
use ChristianBrown\SmartThingsClimate\SupportedMeasurements;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[CoversClass(DeviceReadingBuilder::class)]
#[CoversClass(DeviceReading::class)]
#[CoversClass(Measurement::class)]
#[CoversClass(SupportedMeasurements::class)]
final class DeviceReadingBuilderTest extends TestCase
{
    private const int NOW = 1_800_000_000;

    /**
     * @throws Exception
     */
    public function testHumidityOnlyWithoutRoomOrLabel(): void
    {
        $device = $this->createDevice(null, null);
        $status = $this->createStatus(null, $this->createHumidity(55.0, self::NOW));

        $reading = $this->createBuilder($device, $status, null, self::NOW)
            ->build($device, new SupportedMeasurements(false, true));

        self::assertNotNull($reading);
        self::assertSame('', $reading->getName());
        self::assertNull($reading->getRoomName());
        self::assertNull($reading->getTemperature());
        self::assertSame(55.0, $reading->getHumidity()?->getValue());
    }

    /**
     * @throws Exception
     */
    public function testStalenessIsJudgedAgainstTheClockAtBuildTime(): void
    {
        $device = $this->createDevice('test-device', null);
        $status = $this->createStatus($this->createTemperature(20.0, self::NOW), null);
        $clock = new MockClock('@'.self::NOW);

        $deviceStatusApi = self::createStub(DeviceStatusApiInterface::class);
        $deviceStatusApi->method('getOneByDevice')
            ->willReturn($status);
        $builder = new DeviceReadingBuilder($deviceStatusApi, self::createStub(LocationRoomApiInterface::class), $clock);
        $supported = new SupportedMeasurements(true, false);

        $first = $builder->build($device, $supported);
        $clock->sleep(2 * 24 * 60 * 60);
        $second = $builder->build($device, $supported);

        self::assertFalse($first?->getTemperature()?->isStale());
        self::assertTrue($second?->getTemperature()?->isStale());
    }

    /**
     * @throws Exception
     */
    public function testSupportedMeasurementsWithoutValuesGiveNoReading(): void
    {
        $device = $this->createDevice('test-device', null);
        $status = $this->createStatus(null, null);

        $reading = $this->createBuilder($device, $status, null, self::NOW)
            ->build($device, new SupportedMeasurements(true, true));

        self::assertNull($reading);
    }

    /**
     * @throws Exception
     */
    public function testTemperatureAndHumidityWithRoom(): void
    {
        $device = $this->createDevice('test-device', 'test-room-id');
        $status = $this->createStatus(
            $this->createTemperature(70.0, self::NOW),
            $this->createHumidity(60.0, self::NOW - 604800)
        );

        $reading = $this->createBuilder($device, $status, 'test-room', self::NOW)
            ->build($device, new SupportedMeasurements(true, true));

        self::assertNotNull($reading);
        self::assertSame('test-device', $reading->getName());
        self::assertSame('test-room', $reading->getRoomName());
        $temperature = $reading->getTemperature();
        self::assertNotNull($temperature);
        self::assertSame(70.0, $temperature->getValue());
        self::assertSame(self::NOW, $temperature->getTimestamp());
        self::assertFalse($temperature->isStale());
        $humidity = $reading->getHumidity();
        self::assertNotNull($humidity);
        self::assertSame(60.0, $humidity->getValue());
        self::assertTrue($humidity->isStale());
    }

    /**
     * @throws Exception
     */
    public function testUnsupportedMeasurementsAreIgnored(): void
    {
        $device = $this->createDevice('test-device', null);
        $status = $this->createStatus(
            $this->createTemperature(20.0, self::NOW),
            $this->createHumidity(50.0, self::NOW)
        );

        $reading = $this->createBuilder($device, $status, null, self::NOW)
            ->build($device, new SupportedMeasurements(false, false));

        self::assertNull($reading);
    }

    /**
     * @throws Exception
     */
    private function createBuilder(DeviceInterface $device, DeviceStatusInterface $status, ?string $roomName, int $now): DeviceReadingBuilder
    {
        $deviceStatusApi = self::createStub(DeviceStatusApiInterface::class);
        $deviceStatusApi->method('getOneByDevice')
            ->willReturn($status);

        $room = self::createStub(LocationRoomInterface::class);
        $room->method('getName')
            ->willReturn($roomName);
        $locationRoomApi = self::createStub(LocationRoomApiInterface::class);
        $locationRoomApi->method('getOneByDevice')
            ->willReturn($room);

        return new DeviceReadingBuilder($deviceStatusApi, $locationRoomApi, new MockClock('@'.$now));
    }

    /**
     * @throws Exception
     */
    private function createDevice(?string $label, ?string $roomId): DeviceInterface
    {
        $device = self::createStub(DeviceInterface::class);
        $device->method('getLabel')
            ->willReturn($label);
        $device->method('getRoomId')
            ->willReturn($roomId);

        return $device;
    }

    /**
     * @throws Exception
     */
    private function createHumidity(float $value, int $timestamp): DeviceStatusRelativeHumidityMeasurementInterface
    {
        $humidity = self::createStub(DeviceStatusRelativeHumidityMeasurementHumidityInterface::class);
        $humidity->method('getValue')
            ->willReturn($value);
        $humidity->method('getTimestamp')
            ->willReturn($timestamp);

        $measurement = self::createStub(DeviceStatusRelativeHumidityMeasurementInterface::class);
        $measurement->method('getHumidity')
            ->willReturn($humidity);

        return $measurement;
    }

    /**
     * @throws Exception
     */
    private function createStatus(?DeviceStatusTemperatureMeasurementInterface $temperature, ?DeviceStatusRelativeHumidityMeasurementInterface $humidity): DeviceStatusInterface
    {
        $status = self::createStub(DeviceStatusInterface::class);
        $status->method('getTemperatureMeasurement')
            ->willReturn($temperature);
        $status->method('getRelativeHumidityMeasurement')
            ->willReturn($humidity);

        return $status;
    }

    /**
     * @throws Exception
     */
    private function createTemperature(float $value, int $timestamp): DeviceStatusTemperatureMeasurementInterface
    {
        $temperature = self::createStub(DeviceStatusTemperatureMeasurementTemperatureInterface::class);
        $temperature->method('getValue')
            ->willReturn($value);
        $temperature->method('getTimestamp')
            ->willReturn($timestamp);

        $measurement = self::createStub(DeviceStatusTemperatureMeasurementInterface::class);
        $measurement->method('getTemperature')
            ->willReturn($temperature);

        return $measurement;
    }
}
