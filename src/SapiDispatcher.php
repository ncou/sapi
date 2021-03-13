<?php

declare(strict_types=1);

namespace Chiron\Sapi;

use Chiron\Http\ErrorHandler\HttpErrorHandler;
use Chiron\Core\Dispatcher\AbstractDispatcher;
use Chiron\Sapi\SapiServerRequestCreator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Chiron\Http\Http;
use Throwable;

final class SapiDispatcher extends AbstractDispatcher
{
    /**
     * {@inheritdoc}
     */
    public function canDispatch(): bool
    {
        return PHP_SAPI !== 'cli';
    }

    /**
     * @param SapiListener $sapi
     * @param Http         $http
     * @param ErrorHandler $errorHandler
     */
    protected function perform(SapiListener $sapi, Http $http, HttpErrorHandler $errorHandler): void
    {
        $sapi->onMessage = function (ServerRequestInterface $request) use ($http, $errorHandler) {
           // TODO : code à améliorer pour savoir si on est en debug ou non et donc si les exceptions doivent afficher le détail (stacktrace notamment) !!!!
            // https://github.com/yiisoft/yii-web/blob/ae3d1986fefd41e1f86f345b4ea57ca33326d4f2/src/ErrorHandler/ErrorCatcher.php#L132
            // https://github.com/yiisoft/yii-web/blob/54000c5e34d834efe61dce3ecd6ede36b86c31bd/src/ErrorHandler/ThrowableRendererInterface.php#L28
            $verbose = true;

            // TODO : c'est quoi l'utilité de ce code (le try/catch Throwable) versus le code qui est déjà présent dans le ErrorHandlerMiddleware ????
            try {
                $response = $http->handle($request);
            } catch (Throwable $e) {
                // TODO : il faudrait plutot utiliser le RegisterErrorHandler::renderException($e) pour générer le body de la réponse !!!!
                $response = $errorHandler->renderException($e, $request, $verbose);
            }

            return $response;
        };

        // Emit the response.
        $sapi->listen();
    }
}
