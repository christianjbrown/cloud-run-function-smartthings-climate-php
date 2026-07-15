<?php

declare(strict_types=1);

namespace ChristianBrown\GetSmartHomeTemps;

interface ConfigTransformerInterface
{
    public const ENV_API_TOKEN = 'SMARTTHINGS_API_TOKEN';

    /**
     * @param mixed[] $env
     */
    public function transform(array $env): ConfigInterface;
}
