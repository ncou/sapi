<?php

declare(strict_types=1);

namespace Chiron\Sapi\Bootloader;

use Chiron\Application;
use Chiron\Core\Container\Bootloader\AbstractBootloader;
use Chiron\Sapi\SapiDispatcher;

final class SapiDispatcherBootloader extends AbstractBootloader
{
    public function boot(Application $application): void
    {
        $application->addDispatcher(resolve(SapiDispatcher::class));
    }
}
