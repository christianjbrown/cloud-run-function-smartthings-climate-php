<?php

declare(strict_types=1);

namespace ChristianBrown\SmartThingsClimate;

use ChristianBrown\GcpFunction\FunctionConfigInterface;

final class Config implements ConfigInterface
{
    private string $clientId;
    private string $clientSecret;
    private string $databaseDsn;
    private FunctionConfigInterface $functionConfig;
    private string $locationId;
    private string $tokenUrl;

    public function __construct(FunctionConfigInterface $functionConfig, string $clientId, string $clientSecret, string $databaseDsn, string $locationId, string $tokenUrl)
    {
        $this->functionConfig = $functionConfig;
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->databaseDsn = $databaseDsn;
        $this->locationId = $locationId;
        $this->tokenUrl = $tokenUrl;
    }

    public function getClientId(): string
    {
        return $this->clientId;
    }

    public function getClientSecret(): string
    {
        return $this->clientSecret;
    }

    public function getDatabaseDsn(): string
    {
        return $this->databaseDsn;
    }

    public function getFunctionConfig(): FunctionConfigInterface
    {
        return $this->functionConfig;
    }

    public function getLocationId(): string
    {
        return $this->locationId;
    }

    public function getTokenUrl(): string
    {
        return $this->tokenUrl;
    }
}
