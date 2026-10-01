<?php

declare(strict_types=1);

namespace ChristianBrown\SmartThingsClimate\Tests;

use ChristianBrown\Database\ClimateMeasurementRecorderInterface;
use ChristianBrown\Database\Entity\SmartThingsClimate;
use ChristianBrown\SmartThingsClimate\ClimateAverageCalculatorInterface;
use ChristianBrown\SmartThingsClimate\ClimateRecorder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Clock\MockClock;

use function ini_set;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

#[CoversClass(ClimateRecorder::class)]
final class ClimateRecorderTest extends TestCase
{
    /**
     * @throws Exception
     */
    public function testAverageClimateIsRecordedAtTheClockTime(): void
    {
        $recorded = [];
        $climateMeasurementRecorder = self::createMock(ClimateMeasurementRecorderInterface::class);
        $climateMeasurementRecorder->expects(self::once())
            ->method('record')
            ->willReturnCallback(
                static function (SmartThingsClimate $reading) use (&$recorded): void {
                    $recorded[] = $reading;
                }
            );

        $recorder = new ClimateRecorder($this->createCalculator(21.5, 47.0), $climateMeasurementRecorder, new MockClock('@1800000000'));

        $recorder->record([]);

        self::assertCount(1, $recorded);
        self::assertSame(21.5, $recorded[0]->getTemperature());
        self::assertSame(47.0, $recorded[0]->getHumidity());
        self::assertSame(1800000000, $recorded[0]->getRecordedAt()?->getTimestamp());
    }

    /**
     * @throws Exception
     */
    public function testNothingIsRecordedWhenThereAreNoAverages(): void
    {
        $climateMeasurementRecorder = self::createMock(ClimateMeasurementRecorderInterface::class);
        $climateMeasurementRecorder->expects(self::never())
            ->method('record');

        $recorder = new ClimateRecorder($this->createCalculator(null, null), $climateMeasurementRecorder, new MockClock());

        $recorder->record([]);
    }

    /**
     * @throws Exception
     */
    public function testWriteFailureIsSwallowed(): void
    {
        $climateMeasurementRecorder = self::createMock(ClimateMeasurementRecorderInterface::class);
        $climateMeasurementRecorder->expects(self::once())
            ->method('record')
            ->willThrowException(new RuntimeException('test-database-failure'));

        $recorder = new ClimateRecorder($this->createCalculator(21.5, 47.0), $climateMeasurementRecorder, new MockClock());

        // The write failure is logged via error_log() for Cloud Logging; divert it
        // to a temp file so the strict-output check does not see it as unexpected
        // output.
        $errorLog = (string) tempnam(sys_get_temp_dir(), 'climate-recorder-test');
        $previousErrorLog = (string) ini_set('error_log', $errorLog);

        try {
            $recorder->record([]);
        } finally {
            ini_set('error_log', $previousErrorLog);
            unlink($errorLog);
        }
    }

    /**
     * @throws Exception
     */
    private function createCalculator(?float $temperature, ?float $humidity): ClimateAverageCalculatorInterface
    {
        $calculator = self::createStub(ClimateAverageCalculatorInterface::class);
        $calculator->method('averageTemperature')
            ->willReturn($temperature);
        $calculator->method('averageHumidity')
            ->willReturn($humidity);

        return $calculator;
    }
}
