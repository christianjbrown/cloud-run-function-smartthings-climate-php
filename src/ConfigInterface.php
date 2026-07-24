<?php

declare(strict_types=1);

namespace ChristianBrown\SmartThingsClimate;

use ChristianBrown\CloudRunFunction\FunctionConfigInterface;

interface ConfigInterface
{
    public function getClientId(): string;

    public function getClientSecret(): string;

    public function getDatabaseDsn(): string;

    public function getFunctionConfig(): FunctionConfigInterface;

    public function getLocationId(): string;

    public function getTokenUrl(): string;
}
