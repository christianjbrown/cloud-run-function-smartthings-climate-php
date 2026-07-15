<?php

declare(strict_types=1);

namespace ChristianBrown\GetSmartHomeTemps\Tests;

use ChristianBrown\GetSmartHomeTemps\DataProvider;
use ChristianBrown\GetSmartHomeTemps\DeviceTemperature;
use ChristianBrown\GetSmartHomeTemps\DeviceTemperatureInterface;
use ChristianBrown\GetSmartHomeTemps\OutputTransformerInterface;
use ChristianBrown\SmartThings\Api\DeviceApiInterface;
use ChristianBrown\SmartThings\Api\DeviceStatusApiInterface;
use ChristianBrown\SmartThings\Model\DeviceComponentCapabilityInterface;
use ChristianBrown\SmartThings\Model\DeviceComponentInterface;
use ChristianBrown\SmartThings\Model\DeviceInterface;
use ChristianBrown\SmartThings\Model\DeviceStatusInterface;
use ChristianBrown\SmartThings\Model\DeviceStatusTemperatureMeasurementInterface;
use ChristianBrown\SmartThings\Model\DeviceStatusTemperatureMeasurementTemperatureInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

use function time;

#[CoversClass(DataProvider::class)]
#[CoversClass(DeviceTemperature::class)]
final class DataProviderTest extends TestCase
{
    /**
     * @throws Exception
     */
    public function test(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);

        $device0 = $this->createDevice('test-device-0-no-components', []);

        $device1component1 = $this->createDeviceComponent(false);
        $device1component2 = $this->createDeviceComponent(true);
        $device1 = $this->createDevice('test-device-1-mixed-components-inc-temp', [$device1component1, $device1component2]);

        $device2component1 = $this->createDeviceComponent(false);
        $device2 = $this->createDevice('test-device-2-no-temp', [$device2component1]);

        $device3component1 = $this->createDeviceComponent(true);
        $device3 = $this->createDevice('test-device-3-has-temp-no-value', [$device3component1]);

        $device4component1 = $this->createDeviceComponent(true);
        $device4 = $this->createDevice('test-device-4-has-temp', [$device4component1]);

        $devices = [$device0, $device1, $device2, $device3, $device4];

        $deviceApi = $this->createMock(DeviceApiInterface::class);
        $deviceApi->method('getMultiple')
            ->willReturn($devices);

        $temperatureMeasurement1time = time() - 604800; // stale
        $temperatureMeasurement1 = $this->createDeviceStatusTemperatureMeasurement(42.0, 'C', $temperatureMeasurement1time);
        $temperatureMeasurement4time = time(); // not stale
        $temperatureMeasurement4 = $this->createDeviceStatusTemperatureMeasurement(98.0, 'F', $temperatureMeasurement4time);

        $device1status = $this->createDeviceStatus($temperatureMeasurement1);
        $device3status = $this->createDeviceStatus(null);
        $device4status = $this->createDeviceStatus($temperatureMeasurement4);

        $deviceStatusApi = $this->createMock(DeviceStatusApiInterface::class);
        $deviceStatusApi->expects(self::exactly(3))
            ->method('getOneByDevice')
            ->willReturnMap(
                [
                    [$device1, $device1status],
                    [$device3, $device3status],
                    [$device4, $device4status],
                ]
            );

        $outputTransformer = $this->createMock(OutputTransformerInterface::class);
        $outputTransformer->method('transform')
            ->with(
                self::callback(
                    static function (array $data) use ($temperatureMeasurement1time, $temperatureMeasurement4time) {
                        self::assertCount(2, $data);
                        self::assertArrayHasKey(0, $data);
                        self::assertInstanceOf(DeviceTemperatureInterface::class, $data[0]);
                        self::assertArrayHasKey(1, $data);
                        self::assertInstanceOf(DeviceTemperatureInterface::class, $data[1]);

                        self::assertSame('test-device-1-mixed-components-inc-temp', $data[0]->getLabel());
                        self::assertSame(42.0, $data[0]->getTemperature());
                        self::assertSame($temperatureMeasurement1time, $data[0]->getTimestamp());
                        self::assertTrue($data[0]->isStale());

                        self::assertSame('test-device-4-has-temp', $data[1]->getLabel());
                        self::assertSame(98.0, $data[1]->getTemperature());
                        self::assertSame($temperatureMeasurement4time, $data[1]->getTimestamp());
                        self::assertFalse($data[1]->isStale());

                        return true;
                    }
                )
            )
            ->willReturn(['test-actual-output']);

        $dataProvider = new DataProvider($deviceApi, $deviceStatusApi, $outputTransformer);

        $actual = $dataProvider->getData($request);

        self::assertSame(['test-actual-output'], $actual);
    }

    /**
     * @throws Exception
     */
    private function createDevice(string $label, array $components): DeviceInterface
    {
        $device = $this->createMock(DeviceInterface::class);
        $device->method('getLabel')
            ->willReturn($label);
        $device->method('getComponents')
            ->willReturn($components);

        return $device;
    }

    /**
     * @throws Exception
     */
    private function createDeviceComponent(bool $hasTemperatureMeasurement): DeviceComponentInterface
    {
        $capabilities = [];
        $capabilities[] = $this->createDeviceComponentCapability('test-capability-1');
        if ($hasTemperatureMeasurement) {
            $capabilities[] = $this->createDeviceComponentCapability('temperatureMeasurement');
            $capabilities[] = $this->createDeviceComponentCapability('test-capability-2');
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
    private function createDeviceStatus(?DeviceStatusTemperatureMeasurementInterface $temperatureMeasurement): DeviceStatusInterface
    {
        $deviceStatus = $this->createMock(DeviceStatusInterface::class);
        $deviceStatus->method('getTemperatureMeasurement')
            ->willReturn($temperatureMeasurement);

        return $deviceStatus;
    }

    /**
     * @throws Exception
     */
    private function createDeviceStatusTemperatureMeasurement(float $value, string $unit, int $timestamp): DeviceStatusTemperatureMeasurementInterface
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
