<?php

declare(strict_types=1);

namespace ChristianBrown\SmartThingsClimate;

final class OutputTransformer implements OutputTransformerInterface
{
    private DeviceReadingOutputTransformerInterface $deviceReadingOutputTransformer;

    public function __construct(DeviceReadingOutputTransformerInterface $deviceReadingOutputTransformer)
    {
        $this->deviceReadingOutputTransformer = $deviceReadingOutputTransformer;
    }

    /**
     * @param DeviceReadingInterface[] $deviceReadings
     *
     * @return mixed[]
     */
    public function transform(array $deviceReadings): array
    {
        usort(
            $deviceReadings,
            static fn (DeviceReadingInterface $a, DeviceReadingInterface $b) => strcmp($a->getName(), $b->getName())
        );

        $devicesData = array_map(
            fn (DeviceReadingInterface $deviceReading): array => $this->deviceReadingOutputTransformer->transform($deviceReading),
            $deviceReadings
        );

        return $devicesData;
    }
}
