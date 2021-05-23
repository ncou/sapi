<?php

declare(strict_types=1);

namespace Chiron\Sapi\Bootloader;

use Chiron\Application;
use Chiron\Core\Container\Bootloader\AbstractBootloader;
use Chiron\Container\FactoryInterface;
use Chiron\Sapi\SapiDispatcher;

final class SapiDispatcherBootloader extends AbstractBootloader
{
    public function boot(Application $application, FactoryInterface $factory): void
    {
        $application->addDispatcher($factory->build(SapiDispatcher::class));
    }
}
