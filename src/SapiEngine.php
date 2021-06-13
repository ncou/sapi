<?php

declare(strict_types=1);

namespace Chiron\Sapi;

use Chiron\Http\ErrorHandler\HttpErrorHandler;
use Chiron\Core\Engine\AbstractEngine;
use Chiron\Sapi\SapiServerRequestCreator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Chiron\Http\Http;
use Throwable;

final class SapiEngine extends AbstractEngine
{
    /**
     * {@inheritdoc}
     */
    public function isActive(): bool
    {
        return PHP_SAPI !== 'cli';
    }

    /**
     * @param SapiListener $sapi
     * @param Http         $http
     */
    protected function perform(SapiListener $sapi, Http $http): void
    {
/*
        $sapi->onMessage = function (ServerRequestInterface $request) use ($http) {
            // TODO : code à améliorer pour savoir si on est en debug ou non et donc si les exceptions doivent afficher le détail (stacktrace notamment) !!!!
            // https://github.com/yiisoft/yii-web/blob/ae3d1986fefd41e1f86f345b4ea57ca33326d4f2/src/ErrorHandler/ErrorCatcher.php#L132
            // https://github.com/yiisoft/yii-web/blob/54000c5e34d834efe61dce3ecd6ede36b86c31bd/src/ErrorHandler/ThrowableRendererInterface.php#L28

            // Return a psr7 ResponseInterface.
            return $http->handle($request);
        };
*/


        // Callable used when a new request event is received.
        $sapi->onMessage = [$http, 'handle'];
        // Listen (loop wainting a request) and Emit the response.
        $sapi->listen();
    }
}
