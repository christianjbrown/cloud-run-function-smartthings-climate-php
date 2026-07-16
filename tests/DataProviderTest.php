<?php

declare(strict_types=1);

namespace ChristianBrown\SmartThingsClimate\Tests;

use ChristianBrown\SmartThingsClimate\DataProvider;
use ChristianBrown\SmartThingsClimate\DeviceReading;
use ChristianBrown\SmartThingsClimate\DeviceReadingInterface;
use ChristianBrown\SmartThingsClimate\OutputTransformerInterface;
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
        $request = self::createStub(ServerRequestInterface::class);

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

        $deviceApi = self::createMock(DeviceApiInterface::class);
        $deviceApi->expects(self::once())
            ->method('getMultiple')
            ->with('test-location-id')
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

        $deviceStatusApi = self::createMock(DeviceStatusApiInterface::class);
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
        $locationRoomApi = self::createMock(LocationRoomApiInterface::class);
        $locationRoomApi->expects(self::exactly(3))
            ->method('getOneByDevice')
            ->willReturnMap(
                [
                    [$device4, false, $this->createRoom('test-room-4')],
                    [$device5, false, $this->createRoom('test-room-5')],
                    [$device6, false, $this->createRoom('test-room-6')],
                ]
            );

        $outputTransformer = self::createMock(OutputTransformerInterface::class);
        $outputTransformer->expects(self::once())
            ->method('transform')
            ->with(
                self::callback(
                    static function (array $data) use ($temperature1time, $temperature4time, $temperature6time, $humidity5time, $humidity6time) {
                        self::assertCount(4, $data);

                        // device-1: temperature only, stale
                        self::assertInstanceOf(DeviceReadingInterface::class, $data[0]);
                        self::assertSame('test-device-1-mixed-components-inc-temp', $data[0]->getName());
                        self::assertNull($data[0]->getRoomName());
                        self::assertSame(42.0, $data[0]->getTemperatureValue());
                        self::assertSame($temperature1time, $data[0]->getTemperatureTimestamp());
                        self::assertTrue($data[0]->isTemperatureStale());
                        self::assertNull($data[0]->getHumidityValue());
                        self::assertNull($data[0]->getHumidityTimestamp());
                        self::assertNull($data[0]->isHumidityStale());

                        // device-4: temperature only, fresh
                        self::assertInstanceOf(DeviceReadingInterface::class, $data[1]);
                        self::assertSame('test-device-4-has-temp', $data[1]->getName());
                        self::assertSame('test-room-4', $data[1]->getRoomName());
                        self::assertSame(98.0, $data[1]->getTemperatureValue());
                        self::assertSame($temperature4time, $data[1]->getTemperatureTimestamp());
                        self::assertFalse($data[1]->isTemperatureStale());
                        self::assertNull($data[1]->getHumidityValue());

                        // device-5: humidity only, fresh
                        self::assertInstanceOf(DeviceReadingInterface::class, $data[2]);
                        self::assertSame('test-device-5-humidity-only', $data[2]->getName());
                        self::assertSame('test-room-5', $data[2]->getRoomName());
                        self::assertNull($data[2]->getTemperatureValue());
                        self::assertNull($data[2]->getTemperatureTimestamp());
                        self::assertNull($data[2]->isTemperatureStale());
                        self::assertSame(55.0, $data[2]->getHumidityValue());
                        self::assertSame($humidity5time, $data[2]->getHumidityTimestamp());
                        self::assertFalse($data[2]->isHumidityStale());

                        // device-6: temperature (fresh) and humidity (stale)
                        self::assertInstanceOf(DeviceReadingInterface::class, $data[3]);
                        self::assertSame('test-device-6-temp-and-humidity', $data[3]->getName());
                        self::assertSame('test-room-6', $data[3]->getRoomName());
                        self::assertSame(70.0, $data[3]->getTemperatureValue());
                        self::assertSame($temperature6time, $data[3]->getTemperatureTimestamp());
                        self::assertFalse($data[3]->isTemperatureStale());
                        self::assertSame(60.0, $data[3]->getHumidityValue());
                        self::assertSame($humidity6time, $data[3]->getHumidityTimestamp());
                        self::assertTrue($data[3]->isHumidityStale());

                        return true;
                    }
                )
            )
            ->willReturn(['test-actual-output']);

        $dataProvider = new DataProvider($deviceApi, $deviceStatusApi, $locationRoomApi, $outputTransformer, 'test-location-id');

        $actual = $dataProvider->getData($request);

        self::assertSame(['test-actual-output'], $actual);
    }

    /**
     * @throws Exception
     */
    public function testDeviceComponentWithoutCapabilitiesIsExcluded(): void
    {
        $request = self::createStub(ServerRequestInterface::class);

        $component = self::createStub(DeviceComponentInterface::class);
        $component->method('getCapabilities')
            ->willReturn([]);
        $device = $this->createDevice('test-device', [$component]);

        $deviceApi = self::createStub(DeviceApiInterface::class);
        $deviceApi->method('getMultiple')
            ->willReturn([$device]);

        $deviceStatusApi = self::createStub(DeviceStatusApiInterface::class);
        $locationRoomApi = self::createStub(LocationRoomApiInterface::class);

        $outputTransformer = self::createMock(OutputTransformerInterface::class);
        $outputTransformer->expects(self::once())
            ->method('transform')
            ->with([])
            ->willReturn(['test-actual-output']);

        $dataProvider = new DataProvider($deviceApi, $deviceStatusApi, $locationRoomApi, $outputTransformer, 'test-location-id');

        self::assertSame(['test-actual-output'], $dataProvider->getData($request));
    }

    /**
     * @throws Exception
     */
    public function testDeviceWithHumidityCapabilityButNoReadingIsExcluded(): void
    {
        $request = self::createStub(ServerRequestInterface::class);

        $device = $this->createDevice('test-device', [$this->createDeviceComponent(false, true)]);

        $deviceApi = self::createStub(DeviceApiInterface::class);
        $deviceApi->method('getMultiple')
            ->willReturn([$device]);

        $deviceStatusApi = self::createMock(DeviceStatusApiInterface::class);
        $deviceStatusApi->expects(self::once())
            ->method('getOneByDevice')
            ->with($device)
            ->willReturn($this->createDeviceStatus(null, null));

        $locationRoomApi = self::createStub(LocationRoomApiInterface::class);

        $outputTransformer = self::createMock(OutputTransformerInterface::class);
        $outputTransformer->expects(self::once())
            ->method('transform')
            ->with([])
            ->willReturn(['test-actual-output']);

        $dataProvider = new DataProvider($deviceApi, $deviceStatusApi, $locationRoomApi, $outputTransformer, 'test-location-id');

        self::assertSame(['test-actual-output'], $dataProvider->getData($request));
    }

    /**
     * @throws Exception
     */
    public function testEmptyDeviceListIsTransformed(): void
    {
        $request = self::createStub(ServerRequestInterface::class);

        $deviceApi = self::createStub(DeviceApiInterface::class);
        $deviceApi->method('getMultiple')
            ->willReturn([]);

        $deviceStatusApi = self::createStub(DeviceStatusApiInterface::class);
        $locationRoomApi = self::createStub(LocationRoomApiInterface::class);

        $outputTransformer = self::createMock(OutputTransformerInterface::class);
        $outputTransformer->expects(self::once())
            ->method('transform')
            ->with([])
            ->willReturn(['test-actual-output']);

        $dataProvider = new DataProvider($deviceApi, $deviceStatusApi, $locationRoomApi, $outputTransformer, 'test-location-id');

        self::assertSame(['test-actual-output'], $dataProvider->getData($request));
    }

    /**
     * @throws Exception
     */
    public function testSingleDeviceProducingAReadingIsTransformed(): void
    {
        $request = self::createStub(ServerRequestInterface::class);

        $device = $this->createDevice('test-device', [$this->createDeviceComponent(true, false)], 'test-room-id');

        $deviceApi = self::createStub(DeviceApiInterface::class);
        $deviceApi->method('getMultiple')
            ->willReturn([$device]);

        $deviceStatusApi = self::createMock(DeviceStatusApiInterface::class);
        $deviceStatusApi->expects(self::once())
            ->method('getOneByDevice')
            ->with($device)
            ->willReturn($this->createDeviceStatus($this->createTemperatureMeasurement(20.0, 'C', time()), null));

        $locationRoomApi = self::createMock(LocationRoomApiInterface::class);
        $locationRoomApi->expects(self::once())
            ->method('getOneByDevice')
            ->with($device)
            ->willReturn($this->createRoom('test-room'));

        $outputTransformer = self::createMock(OutputTransformerInterface::class);
        $outputTransformer->expects(self::once())
            ->method('transform')
            ->with(
                self::callback(
                    static function (array $data): bool {
                        self::assertCount(1, $data);
                        self::assertInstanceOf(DeviceReadingInterface::class, $data[0]);
                        self::assertSame('test-device', $data[0]->getName());
                        self::assertSame('test-room', $data[0]->getRoomName());
                        self::assertSame(20.0, $data[0]->getTemperatureValue());

                        return true;
                    }
                )
            )
            ->willReturn(['test-actual-output']);

        $dataProvider = new DataProvider($deviceApi, $deviceStatusApi, $locationRoomApi, $outputTransformer, 'test-location-id');

        self::assertSame(['test-actual-output'], $dataProvider->getData($request));
    }

    /**
     * @throws Exception
     */
    private function createDevice(string $label, array $components, ?string $roomId = null): DeviceInterface
    {
        $device = self::createStub(DeviceInterface::class);
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

        $deviceComponent = self::createStub(DeviceComponentInterface::class);
        $deviceComponent->method('getCapabilities')
            ->willReturn($capabilities);

        return $deviceComponent;
    }

    /**
     * @throws Exception
     */
    private function createDeviceComponentCapability(string $id): DeviceComponentCapabilityInterface
    {
        $capability = self::createStub(DeviceComponentCapabilityInterface::class);
        $capability->method('getId')
            ->willReturn($id);

        return $capability;
    }

    /**
     * @throws Exception
     */
    private function createDeviceStatus(?DeviceStatusTemperatureMeasurementInterface $temperatureMeasurement, ?DeviceStatusRelativeHumidityMeasurementInterface $humidityMeasurement): DeviceStatusInterface
    {
        $deviceStatus = self::createStub(DeviceStatusInterface::class);
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
        $humidity = self::createStub(DeviceStatusRelativeHumidityMeasurementHumidityInterface::class);
        $humidity->method('getValue')
            ->willReturn($value);
        $humidity->method('getTimestamp')
            ->willReturn($timestamp);
        $humidity->method('getUnit')
            ->willReturn($unit);

        $humidityMeasurement = self::createStub(DeviceStatusRelativeHumidityMeasurementInterface::class);
        $humidityMeasurement->method('getHumidity')
            ->willReturn($humidity);

        return $humidityMeasurement;
    }

    /**
     * @throws Exception
     */
    private function createRoom(string $name): LocationRoomInterface
    {
        $room = self::createStub(LocationRoomInterface::class);
        $room->method('getName')
            ->willReturn($name);

        return $room;
    }

    /**
     * @throws Exception
     */
    private function createTemperatureMeasurement(float $value, string $unit, int $timestamp): DeviceStatusTemperatureMeasurementInterface
    {
        $temperature = self::createStub(DeviceStatusTemperatureMeasurementTemperatureInterface::class);
        $temperature->method('getValue')
            ->willReturn($value);
        $temperature->method('getTimestamp')
            ->willReturn($timestamp);
        $temperature->method('getUnit')
            ->willReturn($unit);

        $temperatureMeasurement = self::createStub(DeviceStatusTemperatureMeasurementInterface::class);
        $temperatureMeasurement->method('getTemperature')
            ->willReturn($temperature);

        return $temperatureMeasurement;
    }
}
