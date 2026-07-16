<?php

declare(strict_types=1);

namespace ChristianBrown\SmartThingsClimate\Tests\Database;

use ChristianBrown\SmartThingsClimate\Database\MySqlAdvisoryLock;
use ChristianBrown\SmartThingsClimate\Database\MySqlAdvisoryLockInterface;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function sprintf;

#[CoversClass(MySqlAdvisoryLock::class)]
final class MySqlAdvisoryLockTest extends TestCase
{
    private const string TEST_NAME = 'test-lock';
    private const int TEST_TIMEOUT = 10;

    /**
     * GET_LOCK returns 1, which the driver may give back as an int or a string.
     *
     * @throws Exception
     */
    #[TestWith([1])]
    #[TestWith(['1'])]
    public function testAcquireObtainsTheLock(int|string $lockResult): void
    {
        $connection = self::createMock(Connection::class);
        $connection->expects(self::once())
            ->method('fetchOne')
            ->with(MySqlAdvisoryLockInterface::SQL_GET_LOCK, [self::TEST_NAME, self::TEST_TIMEOUT])
            ->willReturn($lockResult);

        $lock = new MySqlAdvisoryLock($connection, self::TEST_NAME, self::TEST_TIMEOUT);
        $lock->acquire();
    }

    /**
     * @throws Exception
     */
    public function testAcquireThrowsWhenTheLockIsNotObtained(): void
    {
        $connection = self::createMock(Connection::class);
        $connection->expects(self::once())
            ->method('fetchOne')
            ->with(MySqlAdvisoryLockInterface::SQL_GET_LOCK, [self::TEST_NAME, self::TEST_TIMEOUT])
            ->willReturn(0);

        $lock = new MySqlAdvisoryLock($connection, self::TEST_NAME, self::TEST_TIMEOUT);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(sprintf(MySqlAdvisoryLockInterface::ERROR_NOT_ACQUIRED_SPRINTF, self::TEST_NAME, self::TEST_TIMEOUT));
        $lock->acquire();
    }

    /**
     * @throws Exception
     */
    public function testReleaseFreesTheLock(): void
    {
        $connection = self::createMock(Connection::class);
        $connection->expects(self::once())
            ->method('fetchOne')
            ->with(MySqlAdvisoryLockInterface::SQL_RELEASE_LOCK, [self::TEST_NAME]);

        $lock = new MySqlAdvisoryLock($connection, self::TEST_NAME, self::TEST_TIMEOUT);
        $lock->release();
    }
}
