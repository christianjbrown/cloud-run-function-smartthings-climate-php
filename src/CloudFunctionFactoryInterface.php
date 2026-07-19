<?php

declare(strict_types=1);

namespace ChristianBrown\SmartThingsClimate;

use ChristianBrown\GcpFunction\CloudFunctionInterface;

interface CloudFunctionFactoryInterface
{
    public function create(): CloudFunctionInterface;
}
