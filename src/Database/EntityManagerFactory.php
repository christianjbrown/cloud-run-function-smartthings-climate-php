<?php

declare(strict_types=1);

namespace ChristianBrown\SmartThingsClimate\Database;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\MissingMappingDriverImplementation;
use Doctrine\ORM\ORMSetup;

final class EntityManagerFactory implements EntityManagerFactoryInterface
{
    private string $dsn;
    private Configuration $entityConfig;

    public function __construct(string $dsn)
    {
        $this->dsn = $dsn;
        $this->entityConfig = ORMSetup::createAttributeMetadataConfiguration(paths: [__DIR__.'/Entity']);
        $this->entityConfig->enableNativeLazyObjects(true);
    }

    /**
     * @throws MissingMappingDriverImplementation
     * @throws Exception
     */
    public function getEntityManager(): EntityManagerInterface
    {
        $dsnParser = new DsnParser();
        $dbConfig = $dsnParser->parse($this->dsn);
        $connection = DriverManager::getConnection($dbConfig, $this->entityConfig);
        $entityManager = new EntityManager($connection, $this->entityConfig);

        return $entityManager;
    }
}
