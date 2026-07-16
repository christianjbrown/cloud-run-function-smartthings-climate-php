<?php

declare(strict_types=1);

namespace ChristianBrown\SmartThingsClimate\Database;

use Doctrine\DBAL\Connection;
use RuntimeException;

use function sprintf;

/**
 * A named MySQL advisory lock (GET_LOCK / RELEASE_LOCK), used to serialise the
 * OAuth token refresh across instances. The lock is session-scoped, so it must
 * be acquired and released on the same connection the token store uses; MySQL
 * also frees it automatically when that connection closes, so a crash mid-hold
 * cannot wedge the lock permanently.
 */
final class MySqlAdvisoryLock implements MySqlAdvisoryLockInterface
{
    private Connection $connection;
    private string $name;
    private int $timeoutSeconds;

    public function __construct(Connection $connection, string $name, int $timeoutSeconds)
    {
        $this->connection = $connection;
        $this->name = $name;
        $this->timeoutSeconds = $timeoutSeconds;
    }

    public function acquire(): void
    {
        // GET_LOCK returns 1 when the lock is obtained, 0 on timeout, NULL on
        // error. The driver may hand back the 1 as an int or a numeric string,
        // so accept either — with sequential guards (not a compound condition)
        // so each outcome is an independently reachable path.
        $result = $this->connection->fetchOne(self::SQL_GET_LOCK, [$this->name, $this->timeoutSeconds]);
        if (1 === $result) {
            return;
        }
        if ('1' === $result) {
            return;
        }

        throw new RuntimeException(sprintf(self::ERROR_NOT_ACQUIRED_SPRINTF, $this->name, $this->timeoutSeconds));
    }

    public function release(): void
    {
        $this->connection->fetchOne(self::SQL_RELEASE_LOCK, [$this->name]);
    }
}
