<?php

declare(strict_types=1);

namespace ChristianBrown\SmartThingsClimate;

use ChristianBrown\CloudRunFunction\CloudRunFunctionInterface;

interface CloudRunFunctionFactoryInterface
{
    public function create(): CloudRunFunctionInterface;
}
