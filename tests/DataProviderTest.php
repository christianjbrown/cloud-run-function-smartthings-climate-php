<?php

declare(strict_types=1);

namespace ChristianBrown\SmartThingsClimate\Tests;

use ChristianBrown\SmartThings\Model\DeviceInterface;
use ChristianBrown\SmartThingsClimate\ClimateRecorderInterface;
use ChristianBrown\SmartThingsClimate\DataProvider;
use ChristianBrown\SmartThingsClimate\DeviceFetcherInterface;
use ChristianBrown\SmartThingsClimate\DeviceReadingBuilderInterface;
use ChristianBrown\SmartThingsClimate\DeviceReadingInterface;
use ChristianBrown\SmartThingsClimate\MeasurementCapabilityDetectorInterface;
use ChristianBrown\SmartThingsClimate\OutputTransformerInterface;
use ChristianBrown\SmartThingsClimate\SupportedMeasurements;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

#[CoversClass(DataProvider::class)]
#[CoversClass(SupportedMeasurements::class)]
final class DataProviderTest extends TestCase
{
    /**
     * @throws Exception
     */
    public function test(): void
    {
        $request = self::createStub(ServerRequestInterface::class);

        $unsupportedDevice = self::createStub(DeviceInterface::class);
        $noReadingDevice = self::createStub(DeviceInterface::class);
        $readingDevice = self::createStub(DeviceInterface::class);
        $reading = self::createStub(DeviceReadingInterface::class);

        $deviceFetcher = self::createMock(DeviceFetcherInterface::class);
        $deviceFetcher->expects(self::once())
            ->method('fetch')
            ->willReturn([$unsupportedDevice, $noReadingDevice, $readingDevice]);

        $supported = new SupportedMeasurements(true, false);
        $detector = self::createStub(MeasurementCapabilityDetectorInterface::class);
        $detector->method('detect')
            ->willReturnMap(
                [
                    [$unsupportedDevice, new SupportedMeasurements(false, false)],
                    [$noReadingDevice, $supported],
                    [$readingDevice, $supported],
                ]
            );

        // Only devices that support a measurement reach the builder.
        $builder = self::createMock(DeviceReadingBuilderInterface::class);
        $builder->expects(self::exactly(2))
            ->method('build')
            ->willReturnMap(
                [
                    [$noReadingDevice, $supported, null],
                    [$readingDevice, $supported, $reading],
                ]
            );

        $climateRecorder = self::createMock(ClimateRecorderInterface::class);
        $climateRecorder->expects(self::once())
            ->method('record')
            ->with([$reading]);

        $outputTransformer = self::createMock(OutputTransformerInterface::class);
        $outputTransformer->expects(self::once())
            ->method('transform')
            ->with([$reading])
            ->willReturn(['test-actual-output']);

        $dataProvider = new DataProvider($deviceFetcher, $detector, $builder, $climateRecorder, $outputTransformer);

        self::assertSame(['test-actual-output'], $dataProvider->getData($request));
    }

    /**
     * @throws Exception
     */
    public function testEmptyDeviceListIsTransformedAndRecorded(): void
    {
        $request = self::createStub(ServerRequestInterface::class);

        $deviceFetcher = self::createStub(DeviceFetcherInterface::class);
        $deviceFetcher->method('fetch')
            ->willReturn([]);

        $climateRecorder = self::createMock(ClimateRecorderInterface::class);
        $climateRecorder->expects(self::once())
            ->method('record')
            ->with([]);

        $outputTransformer = self::createMock(OutputTransformerInterface::class);
        $outputTransformer->expects(self::once())
            ->method('transform')
            ->with([])
            ->willReturn(['test-actual-output']);

        $dataProvider = new DataProvider(
            $deviceFetcher,
            self::createStub(MeasurementCapabilityDetectorInterface::class),
            self::createStub(DeviceReadingBuilderInterface::class),
            $climateRecorder,
            $outputTransformer
        );

        self::assertSame(['test-actual-output'], $dataProvider->getData($request));
    }
}
