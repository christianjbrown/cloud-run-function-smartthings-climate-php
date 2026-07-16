<?php

declare(strict_types=1);

namespace ChristianBrown\SmartThingsClimate\Tests\Database;

use ChristianBrown\SmartThingsClimate\Database\EntityManagerFactory;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\MissingMappingDriverImplementation;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(EntityManagerFactory::class)]
final class EntityManagerFactoryTest extends TestCase
{
    /**
     * @throws MissingMappingDriverImplementation
     * @throws Exception
     */
    public function testGetEntityManager(): void
    {
        $entityManagerFactory = new EntityManagerFactory('sqlite3:///:memory:');
        $entityManager = $entityManagerFactory->getEntityManager();
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
    }
}
