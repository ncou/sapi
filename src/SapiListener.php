<?php

declare(strict_types=1);

namespace Chiron\Sapi;

use Chiron\Http\ErrorHandler\HttpErrorHandler;
use Chiron\Core\Dispatcher\AbstractDispatcher;
use Nyholm\Psr7Server\ServerRequestCreator;
use Chiron\Http\Http;
use Chiron\Sapi\Exception\HeadersAlreadySentException;
use Throwable;

// TODO : renommer la classe en SapiResponder ? car ce n'est pas vraiment un listener !!!! + renommer la méthode listen() en respond()
final class SapiListener
{
    /** @var callable */
    public $onMessage; // TODO : forcer une \Closure ????
    /** @var ServerRequestCreator */
    private ServerRequestCreator $requestCreator;
    /** @var SapiEmitter */
    private SapiEmitter $emitter;

    public function __construct(ServerRequestCreator $requestCreator, SapiEmitter $emitter)
    {
        $this->requestCreator = $requestCreator;
        $this->emitter = $emitter;
    }

    public function listen(): void
    {
        $request = $this->requestCreator->fromGlobals();
        $response = call_user_func($this->onMessage, $request); // TODO : si on utilise une closure dans ce cas utiliser le code suivant pour l'execution : ($this->onMessage)($request)

        // Emit the response body and HTTP headers.
        $this->emitter->emit($response);
    }
}
