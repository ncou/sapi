<?php

declare(strict_types=1);

namespace Chiron\Sapi;

use Chiron\Http\ErrorHandler\HttpErrorHandler;
use Chiron\Core\Dispatcher\AbstractDispatcher;
use Nyholm\Psr7Server\ServerRequestCreator;
use Chiron\Http\Http;
use Chiron\Sapi\Exception\HeadersAlreadySentException;
use Throwable;
use Chiron\Http\Message\StatusCode;
use Chiron\Http\Message\RequestMethod;

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

        // Response to HEAD and 1xx, 204 and 304 responses MUST NOT include a body.
        $code = $response->getStatusCode();
        $method = $request->getMethod();

        if ($method === RequestMethod::HEAD || StatusCode::isInformational($code) || StatusCode::isEmpty($code)) {
            $this->emitter->emit($response, withBody: false);
        } else {
            $this->emitter->emit($response, withBody: true);
        }
    }
}
