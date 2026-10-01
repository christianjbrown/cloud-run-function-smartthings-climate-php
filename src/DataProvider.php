<?php

declare(strict_types=1);

namespace ChristianBrown\SmartThingsClimate;

use ChristianBrown\SmartThings\Model\DeviceInterface;
use Psr\Http\Message\ServerRequestInterface;

use function array_filter;
use function array_map;
use function array_values;

final class DataProvider implements DataProviderInterface
{
    private ClimateRecorderInterface $climateRecorder;
    private DeviceFetcherInterface $deviceFetcher;
    private DeviceReadingBuilderInterface $deviceReadingBuilder;
    private MeasurementCapabilityDetectorInterface $measurementCapabilityDetector;
    private OutputTransformerInterface $outputTransformer;

    public function __construct(DeviceFetcherInterface $deviceFetcher, MeasurementCapabilityDetectorInterface $measurementCapabilityDetector, DeviceReadingBuilderInterface $deviceReadingBuilder, ClimateRecorderInterface $climateRecorder, OutputTransformerInterface $outputTransformer)
    {
        $this->climateRecorder = $climateRecorder;
        $this->deviceFetcher = $deviceFetcher;
        $this->deviceReadingBuilder = $deviceReadingBuilder;
        $this->measurementCapabilityDetector = $measurementCapabilityDetector;
        $this->outputTransformer = $outputTransformer;
    }

    /**
     * @return mixed[]
     */
    public function getData(ServerRequestInterface $request): array
    {
        $readings = array_values(array_filter(array_map(
            fn (DeviceInterface $device): ?DeviceReadingInterface => $this->processDevice($device),
            $this->deviceFetcher->fetch()
        )));

        $this->climateRecorder->record($readings);

        return $this->outputTransformer->transform($readings);
    }

    private function processDevice(DeviceInterface $device): ?DeviceReadingInterface
    {
        $supportedMeasurements = $this->measurementCapabilityDetector->detect($device);
        if (!$supportedMeasurements->hasAny()) {
            return null;
        }

        return $this->deviceReadingBuilder->build($device, $supportedMeasurements);
    }
}
