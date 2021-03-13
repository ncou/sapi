<?php

declare(strict_types=1);

namespace Chiron\Sapi;

use Chiron\Http\ErrorHandler\HttpErrorHandler;
use Chiron\Core\Dispatcher\AbstractDispatcher;
use Nyholm\Psr7Server\ServerRequestCreator;
use Chiron\Http\Http;
use Throwable;

final class SapiListener
{
    /** @var callable */
    public $onMessage;
    /** @var ServerRequestCreator */
    private $requestCreator;
    /** @var SapiEmitter */
    private $emitter;

    public function __construct(ServerRequestCreator $requestCreator, SapiEmitter $emitter)
    {
        $this->requestCreator = $requestCreator;
        $this->emitter = $emitter;
    }

    public function listen(): void
    {
        $request = $this->requestCreator->fromGlobals();
        $response = call_user_func($this->onMessage, $request);

        $this->emitter->emit($response); // TODO : indiquer dans la phpdoc qu'il peut il y avoir une exception lorsque le emitter a déjà envoyé les headers ????
    }
}
