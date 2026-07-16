<?php

declare(strict_types=1);

namespace ChristianBrown\SmartThingsClimate\Database;

use ChristianBrown\OAuth2Client\Lock\LockInterface;

interface MySqlAdvisoryLockInterface extends LockInterface
{
    public const ERROR_NOT_ACQUIRED_SPRINTF = 'Could not acquire the advisory lock "%s" within %d seconds';
    public const SQL_GET_LOCK = 'SELECT GET_LOCK(?, ?)';
    public const SQL_RELEASE_LOCK = 'SELECT RELEASE_LOCK(?)';
}
