<?php

declare(strict_types=1);

namespace ChristianBrown\SmartThingsClimate\Tests;

use ChristianBrown\SmartThingsClimate\DeviceReadingInterface;
use ChristianBrown\SmartThingsClimate\DeviceReadingOutputTransformerInterface;
use ChristianBrown\SmartThingsClimate\OutputTransformer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\TestCase;

#[CoversClass(OutputTransformer::class)]
final class OutputTransformerTest extends TestCase
{
    /**
     * @throws Exception
     */
    public function testEmptyReadingsProducesAnEmptyArray(): void
    {
        $deviceReadingOutputTransformer = self::createStub(DeviceReadingOutputTransformerInterface::class);

        $transformer = new OutputTransformer($deviceReadingOutputTransformer);

        $expected = [];

        $actual = $transformer->transform([]);

        self::assertSame($expected, $actual);
    }

    /**
     * @throws Exception
     */
    public function testReturnsTheTransformedDevicesSortedByName(): void
    {
        $reading2 = $this->createReading('b-device');
        $reading1 = $this->createReading('a-device');

        $deviceReadingOutputTransformer = self::createStub(DeviceReadingOutputTransformerInterface::class);
        $deviceReadingOutputTransformer->method('transform')
            ->willReturnMap(
                [
                    [$reading1, ['a-device']],
                    [$reading2, ['b-device']],
                ]
            );

        $transformer = new OutputTransformer($deviceReadingOutputTransformer);

        $expected = [
            ['a-device'],
            ['b-device'],
        ];

        // Passed out of order to exercise the sort.
        $actual = $transformer->transform([$reading2, $reading1]);

        self::assertSame($expected, $actual);
    }

    /**
     * @throws Exception
     */
    private function createReading(string $label): DeviceReadingInterface
    {
        $reading = self::createStub(DeviceReadingInterface::class);
        $reading->method('getName')
            ->willReturn($label);

        return $reading;
    }
}
