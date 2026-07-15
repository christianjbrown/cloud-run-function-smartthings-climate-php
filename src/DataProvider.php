<?php

declare(strict_types=1);

namespace ChristianBrown\GetSmartHomeTemps;

use ChristianBrown\SmartThings\Api\DeviceApiInterface;
use ChristianBrown\SmartThings\Api\DeviceStatusApiInterface;
use ChristianBrown\SmartThings\Model\DeviceComponentCapabilityInterface;
use ChristianBrown\SmartThings\Model\DeviceComponentInterface;
use ChristianBrown\SmartThings\Model\DeviceInterface;
use ChristianBrown\SmartThings\Model\DeviceStatusTemperatureMeasurementInterface;
use Psr\Http\Message\ServerRequestInterface;

final class DataProvider implements DataProviderInterface
{
    private DeviceApiInterface $devicesApi;
    private DeviceStatusApiInterface $deviceStatusApi;
    private int $now;
    private OutputTransformerInterface $outputTransformer;

    public function __construct(DeviceApiInterface $devicesApi, DeviceStatusApiInterface $deviceStatusApi, OutputTransformerInterface $outputTransformer)
    {
        $this->devicesApi = $devicesApi;
        $this->deviceStatusApi = $deviceStatusApi;
        $this->outputTransformer = $outputTransformer;
        $this->now = time();
    }

    public function getData(ServerRequestInterface $request): array
    {
        $devices = $this->devicesApi->getMultiple();
        $deviceTemperatures = [];

        foreach ($devices as $device) {
            $temperatureData = $this->processDeviceForTemperature($device);
            if ($temperatureData instanceof DeviceTemperatureInterface) {
                $deviceTemperatures[] = $temperatureData;
            }
        }

        $data = $this->outputTransformer->transform($deviceTemperatures);

        return $data;
    }

    private function getTemperatureMeasurementData(DeviceInterface $device): ?DeviceTemperatureInterface
    {
        $deviceStatus = $this->deviceStatusApi->getOneByDevice($device);
        $temperatureMeasurement = $deviceStatus->getTemperatureMeasurement();
        if (!($temperatureMeasurement instanceof DeviceStatusTemperatureMeasurementInterface)) {
            return null;
        }

        $temperature = $temperatureMeasurement->getTemperature();
        $timestamp = $temperature->getTimestamp();
        $stale = $timestamp < $this->now - self::STALE_TEMPERATURE_THRESHOLD;

        $obj = new DeviceTemperature($device->getLabel(), $temperature->getValue(), $timestamp, $stale);

        return $obj;
    }

    private function processDeviceForTemperature(DeviceInterface $device): ?DeviceTemperature
    {
        if (!$device->getComponents()) {
            return null;
        }

        foreach ($device->getComponents() as $component) {
            if ($component instanceof DeviceComponentInterface) {
                foreach ($component->getCapabilities() as $capability) {
                    if ($capability instanceof DeviceComponentCapabilityInterface && self::ID_VALUE_TEMPERATURE_MEASUREMENT === $capability->getId()) {
                        return $this->getTemperatureMeasurementData($device);
                    }
                }
            }
        }

        return null;
    }
}
