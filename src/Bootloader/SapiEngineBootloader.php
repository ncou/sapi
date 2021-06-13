<?php

declare(strict_types=1);

namespace Chiron\Sapi\Bootloader;

use Chiron\Application;
use Chiron\Core\Container\Bootloader\AbstractBootloader;
use Chiron\Container\FactoryInterface;
use Chiron\Sapi\SapiEngine;

final class SapiEngineBootloader extends AbstractBootloader
{
    public function boot(Application $application, FactoryInterface $factory): void
    {
        $application->addEngine($factory->build(SapiEngine::class));
    }
}
