<?php

declare(strict_types=1);

namespace ChristianBrown\SmartThingsClimate\Database\Entity;

use ChristianBrown\KeyValueStore\AbstractDatabaseKeyValueStoreEntity;
use Doctrine\ORM\Mapping as ORM;

/**
 * A row in the shared `refresh_tokens` key-value table. The SmartThings
 * function stores its rotating OAuth access and refresh tokens here, keyed by
 * distinct ids, so credentials survive across invocations and instances.
 */
#[ORM\Entity]
#[ORM\Table(name: 'refresh_tokens')]
class RefreshToken extends AbstractDatabaseKeyValueStoreEntity
{
}
