<?php

declare(strict_types=1);

namespace ChristianBrown\SmartThingsClimate\Tests;

use ChristianBrown\SmartThingsClimate\SupportedMeasurements;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(SupportedMeasurements::class)]
final class SupportedMeasurementsTest extends TestCase
{
    #[DataProvider('provideCases')]
    public function test(bool $temperature, bool $humidity, bool $any): void
    {
        $supported = new SupportedMeasurements($temperature, $humidity);

        self::assertSame($temperature, $supported->supportsTemperature());
        self::assertSame($humidity, $supported->supportsHumidity());
        self::assertSame($any, $supported->hasAny());
    }

    /**
     * @return iterable<string, array{0: bool, 1: bool, 2: bool}>
     */
    public static function provideCases(): iterable
    {
        yield 'none' => [false, false, false];
        yield 'temperature only' => [true, false, true];
        yield 'humidity only' => [false, true, true];
        yield 'both' => [true, true, true];
    }
}
