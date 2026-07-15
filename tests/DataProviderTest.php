<?php

declare(strict_types=1);

namespace ChristianBrown\GetSmartHomeTemps\Tests;

use ChristianBrown\GetSmartHomeTemps\DataProvider;
use ChristianBrown\GetSmartHomeTemps\DeviceReading;
use ChristianBrown\GetSmartHomeTemps\DeviceReadingInterface;
use ChristianBrown\GetSmartHomeTemps\OutputTransformerInterface;
use ChristianBrown\SmartThings\Api\DeviceApiInterface;
use ChristianBrown\SmartThings\Api\DeviceStatusApiInterface;
use ChristianBrown\SmartThings\Api\LocationRoomApiInterface;
use ChristianBrown\SmartThings\Model\DeviceComponentCapabilityInterface;
use ChristianBrown\SmartThings\Model\DeviceComponentInterface;
use ChristianBrown\SmartThings\Model\DeviceInterface;
use ChristianBrown\SmartThings\Model\DeviceStatusInterface;
use ChristianBrown\SmartThings\Model\DeviceStatusRelativeHumidityMeasurementHumidityInterface;
use ChristianBrown\SmartThings\Model\DeviceStatusRelativeHumidityMeasurementInterface;
use ChristianBrown\SmartThings\Model\DeviceStatusTemperatureMeasurementInterface;
use ChristianBrown\SmartThings\Model\DeviceStatusTemperatureMeasurementTemperatureInterface;
use ChristianBrown\SmartThings\Model\LocationRoomInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

use function time;

#[CoversClass(DataProvider::class)]
#[CoversClass(DeviceReading::class)]
final class DataProviderTest extends TestCase
{
    /**
     * @throws Exception
     */
    public function test(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);

        $device0 = $this->createDevice('test-device-0-no-components', []);

        $device1component1 = $this->createDeviceComponent(false, false);
        $device1component2 = $this->createDeviceComponent(true, false);
        $device1 = $this->createDevice('test-device-1-mixed-components-inc-temp', [$device1component1, $device1component2]);

        $device2component1 = $this->createDeviceComponent(false, false);
        $device2 = $this->createDevice('test-device-2-no-temp-no-humidity', [$device2component1]);

        $device3component1 = $this->createDeviceComponent(true, false);
        $device3 = $this->createDevice('test-device-3-has-temp-no-value', [$device3component1]);

        $device4component1 = $this->createDeviceComponent(true, false);
        $device4 = $this->createDevice('test-device-4-has-temp', [$device4component1], 'test-room-id-4');

        $device5component1 = $this->createDeviceComponent(false, true);
        $device5 = $this->createDevice('test-device-5-humidity-only', [$device5component1], 'test-room-id-5');

        $device6component1 = $this->createDeviceComponent(true, true);
        $device6 = $this->createDevice('test-device-6-temp-and-humidity', [$device6component1], 'test-room-id-6');

        $devices = [$device0, $device1, $device2, $device3, $device4, $device5, $device6];

        $deviceApi = $this->createMock(DeviceApiInterface::class);
        $deviceApi->method('getMultiple')
            ->willReturn($devices);

        $temperature1time = time() - 604800; // stale
        $temperatureMeasurement1 = $this->createTemperatureMeasurement(42.0, 'C', $temperature1time);
        $temperature4time = time(); // not stale
        $temperatureMeasurement4 = $this->createTemperatureMeasurement(98.0, 'F', $temperature4time);
        $temperature6time = time(); // not stale
        $temperatureMeasurement6 = $this->createTemperatureMeasurement(70.0, 'F', $temperature6time);

        $humidity5time = time(); // not stale
        $humidityMeasurement5 = $this->createHumidityMeasurement(55.0, '%', $humidity5time);
        $humidity6time = time() - 604800; // stale
        $humidityMeasurement6 = $this->createHumidityMeasurement(60.0, '%', $humidity6time);

        $device1status = $this->createDeviceStatus($temperatureMeasurement1, null);
        $device3status = $this->createDeviceStatus(null, null);
        $device4status = $this->createDeviceStatus($temperatureMeasurement4, null);
        $device5status = $this->createDeviceStatus(null, $humidityMeasurement5);
        $device6status = $this->createDeviceStatus($temperatureMeasurement6, $humidityMeasurement6);

        $deviceStatusApi = $this->createMock(DeviceStatusApiInterface::class);
        $deviceStatusApi->expects(self::exactly(5))
            ->method('getOneByDevice')
            ->willReturnMap(
                [
                    [$device1, $device1status],
                    [$device3, $device3status],
                    [$device4, $device4status],
                    [$device5, $device5status],
                    [$device6, $device6status],
                ]
            );

        // Only devices that are in a room (and produce a reading) are looked up.
        $locationRoomApi = $this->createMock(LocationRoomApiInterface::class);
        $locationRoomApi->expects(self::exactly(3))
            ->method('getOneByDevice')
            ->willReturnMap(
                [
                    [$device4, false, $this->createRoom('test-room-4')],
                    [$device5, false, $this->createRoom('test-room-5')],
                    [$device6, false, $this->createRoom('test-room-6')],
                ]
            );

        $outputTransformer = $this->createMock(OutputTransformerInterface::class);
        $outputTransformer->method('transform')
            ->with(
                self::callback(
                    static function (array $data) use ($temperature1time, $temperature4time, $temperature6time, $humidity5time, $humidity6time) {
                        self::assertCount(4, $data);

                        // device-1: temperature only, stale
                        self::assertInstanceOf(DeviceReadingInterface::class, $data[0]);
                        self::assertSame('test-device-1-mixed-components-inc-temp', $data[0]->getLabel());
                        self::assertNull($data[0]->getRoomName());
                        self::assertSame(42.0, $data[0]->getTemperature());
                        self::assertSame($temperature1time, $data[0]->getTimestamp());
                        self::assertTrue($data[0]->isStale());
                        self::assertNull($data[0]->getHumidity());
                        self::assertNull($data[0]->getHumidityTimestamp());
                        self::assertNull($data[0]->isHumidityStale());

                        // device-4: temperature only, fresh
                        self::assertInstanceOf(DeviceReadingInterface::class, $data[1]);
                        self::assertSame('test-device-4-has-temp', $data[1]->getLabel());
                        self::assertSame('test-room-4', $data[1]->getRoomName());
                        self::assertSame(98.0, $data[1]->getTemperature());
                        self::assertSame($temperature4time, $data[1]->getTimestamp());
                        self::assertFalse($data[1]->isStale());
                        self::assertNull($data[1]->getHumidity());

                        // device-5: humidity only, fresh
                        self::assertInstanceOf(DeviceReadingInterface::class, $data[2]);
                        self::assertSame('test-device-5-humidity-only', $data[2]->getLabel());
                        self::assertSame('test-room-5', $data[2]->getRoomName());
                        self::assertNull($data[2]->getTemperature());
                        self::assertNull($data[2]->getTimestamp());
                        self::assertNull($data[2]->isStale());
                        self::assertSame(55.0, $data[2]->getHumidity());
                        self::assertSame($humidity5time, $data[2]->getHumidityTimestamp());
                        self::assertFalse($data[2]->isHumidityStale());

                        // device-6: temperature (fresh) and humidity (stale)
                        self::assertInstanceOf(DeviceReadingInterface::class, $data[3]);
                        self::assertSame('test-device-6-temp-and-humidity', $data[3]->getLabel());
                        self::assertSame('test-room-6', $data[3]->getRoomName());
                        self::assertSame(70.0, $data[3]->getTemperature());
                        self::assertSame($temperature6time, $data[3]->getTimestamp());
                        self::assertFalse($data[3]->isStale());
                        self::assertSame(60.0, $data[3]->getHumidity());
                        self::assertSame($humidity6time, $data[3]->getHumidityTimestamp());
                        self::assertTrue($data[3]->isHumidityStale());

                        return true;
                    }
                )
            )
            ->willReturn(['test-actual-output']);

        $dataProvider = new DataProvider($deviceApi, $deviceStatusApi, $locationRoomApi, $outputTransformer);

        $actual = $dataProvider->getData($request);

        self::assertSame(['test-actual-output'], $actual);
    }

    /**
     * @throws Exception
     */
    private function createDevice(string $label, array $components, ?string $roomId = null): DeviceInterface
    {
        $device = $this->createMock(DeviceInterface::class);
        $device->method('getLabel')
            ->willReturn($label);
        $device->method('getComponents')
            ->willReturn($components);
        $device->method('getRoomId')
            ->willReturn($roomId);

        return $device;
    }

    /**
     * @throws Exception
     */
    private function createRoom(string $name): LocationRoomInterface
    {
        $room = $this->createMock(LocationRoomInterface::class);
        $room->method('getName')
            ->willReturn($name);

        return $room;
    }

    /**
     * @throws Exception
     */
    private function createDeviceComponent(bool $hasTemperatureMeasurement, bool $hasHumidityMeasurement): DeviceComponentInterface
    {
        $capabilities = [];
        $capabilities[] = $this->createDeviceComponentCapability('test-capability-1');
        if ($hasTemperatureMeasurement) {
            $capabilities[] = $this->createDeviceComponentCapability('temperatureMeasurement');
            $capabilities[] = $this->createDeviceComponentCapability('test-capability-2');
        }
        if ($hasHumidityMeasurement) {
            $capabilities[] = $this->createDeviceComponentCapability('relativeHumidityMeasurement');
            $capabilities[] = $this->createDeviceComponentCapability('test-capability-3');
        }

        $deviceComponent = $this->createMock(DeviceComponentInterface::class);
        $deviceComponent->method('getCapabilities')
            ->willReturn($capabilities);

        return $deviceComponent;
    }

    /**
     * @throws Exception
     */
    private function createDeviceComponentCapability(string $id): DeviceComponentCapabilityInterface
    {
        $capability = $this->createMock(DeviceComponentCapabilityInterface::class);
        $capability->method('getId')
            ->willReturn($id);

        return $capability;
    }

    /**
     * @throws Exception
     */
    private function createDeviceStatus(?DeviceStatusTemperatureMeasurementInterface $temperatureMeasurement, ?DeviceStatusRelativeHumidityMeasurementInterface $humidityMeasurement): DeviceStatusInterface
    {
        $deviceStatus = $this->createMock(DeviceStatusInterface::class);
        $deviceStatus->method('getTemperatureMeasurement')
            ->willReturn($temperatureMeasurement);
        $deviceStatus->method('getRelativeHumidityMeasurement')
            ->willReturn($humidityMeasurement);

        return $deviceStatus;
    }

    /**
     * @throws Exception
     */
    private function createHumidityMeasurement(float $value, string $unit, int $timestamp): DeviceStatusRelativeHumidityMeasurementInterface
    {
        $humidity = $this->createMock(DeviceStatusRelativeHumidityMeasurementHumidityInterface::class);
        $humidity->method('getValue')
            ->willReturn($value);
        $humidity->method('getTimestamp')
            ->willReturn($timestamp);
        $humidity->method('getUnit')
            ->willReturn($unit);

        $humidityMeasurement = $this->createMock(DeviceStatusRelativeHumidityMeasurementInterface::class);
        $humidityMeasurement->method('getHumidity')
            ->willReturn($humidity);

        return $humidityMeasurement;
    }

    /**
     * @throws Exception
     */
    private function createTemperatureMeasurement(float $value, string $unit, int $timestamp): DeviceStatusTemperatureMeasurementInterface
    {
        $temperature = $this->createMock(DeviceStatusTemperatureMeasurementTemperatureInterface::class);
        $temperature->method('getValue')
            ->willReturn($value);
        $temperature->method('getTimestamp')
            ->willReturn($timestamp);
        $temperature->method('getUnit')
            ->willReturn($unit);

        $temperatureMeasurement = $this->createMock(DeviceStatusTemperatureMeasurementInterface::class);
        $temperatureMeasurement->method('getTemperature')
            ->willReturn($temperature);

        return $temperatureMeasurement;
    }
}
