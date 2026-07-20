<?php

declare(strict_types=1);

namespace ChristianBrown\SmartThingsClimate;

interface ConfigTransformerInterface
{
    public const string ENV_CLIENT_ID = 'SMARTTHINGS_OAUTH_CLIENT_ID';
    public const string ENV_CLIENT_SECRET = 'SMARTTHINGS_OAUTH_CLIENT_SECRET';
    public const string ENV_DATABASE_DSN = 'SMARTTHINGS_DATABASE_DSN';
    public const string ENV_LOCATION_ID = 'SMARTTHINGS_LOCATION_ID';
    public const string ENV_TOKEN_URL = 'SMARTTHINGS_OAUTH_TOKEN_URL';

    /**
     * @param mixed[] $env
     */
    public function transform(array $env): ConfigInterface;
}
