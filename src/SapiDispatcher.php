<?php

declare(strict_types=1);

namespace Chiron\Sapi;

use Chiron\Http\ErrorHandler\HttpErrorHandler;
use Chiron\Core\Dispatcher\AbstractDispatcher;
use Chiron\Sapi\SapiServerRequestCreator;
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
     * @param Http         $http
     * @param SapiEmitter  $emitter
     * @param ErrorHandler $errorHandler
     */
    // TODO : utiliser plutot un ErrorHandlerInterface au lieu de l'objet ErrorHandler !!!!
    protected function perform(Http $http, SapiEmitter $emitter, HttpErrorHandler $errorHandler): void
    {
        $request = SapiServerRequestCreator::fromGlobals();

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

        // TODO : il faudrait pas remonter le emit dans le try/catch pour éviter de traiter l'erreur dans le error handler générique (celui dans RegisterErrorHandler) mais d'utiliser celui qui est dans le catch. notamment pour afficher une erreur 500 classique si l'utilisateur a déjà envoyé les headers (send_headers()) et donc que la classe SapiEmitter remonte une Exception !!!!
        $emitter->emit($response);
    }
}
