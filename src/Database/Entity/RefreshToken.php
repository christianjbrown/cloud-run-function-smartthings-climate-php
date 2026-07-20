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
// A Doctrine entity is deliberately non-final (proxy/lazy hydration), so the
// "abstract or final" rule is suppressed for this class only.
// phpcs:disable SlevomatCodingStandard.Classes.RequireAbstractOrFinal.ClassNeitherAbstractNorFinal
class RefreshToken extends AbstractDatabaseKeyValueStoreEntity
{
}
// phpcs:enable SlevomatCodingStandard.Classes.RequireAbstractOrFinal.ClassNeitherAbstractNorFinal
