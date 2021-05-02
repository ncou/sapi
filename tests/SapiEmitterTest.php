<?php

declare(strict_types=1);

// TODO : utiliser cette classe pour ajouter des tests : https://github.com/zendframework/zend-diactoros/blob/master/test/Response/SapiStreamEmitterTest.php
// https://github.com/cakephp/cakephp/blob/master/tests/TestCase/Http/ResponseEmitterTest.php

namespace Chiron\Sapi\Test;

use Chiron\Sapi\SapiEmitter;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use Chiron\Sapi\Test\Utils\HeaderStack;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;

class SapiEmitterTest extends TestCase
{
    private $emitter;

    /**
     * Setup.
     */
    protected function setUp(): void
    {
        $this->emitter = new SapiEmitter();
        HeaderStack::reset();
    }

    protected function tearDown(): void
    {
        HeaderStack::reset();
    }

    public function testEmitsResponseHeaders(): void
    {
        $response = (new Response())
                ->withStatus(200)
                ->withAddedHeader('Content-Type', 'text/plain');

        ob_start();
        $this->emitter->emit($response);
        ob_end_clean();

        self::assertTrue(HeaderStack::has('HTTP/1.1 200 OK'));
        self::assertTrue(HeaderStack::has('Content-Type: text/plain'));
    }

    public function testEmitsMessageBody(): void
    {
        $response = (new Response())
                ->withStatus(200)
                ->withAddedHeader('Content-Type', 'text/plain');
        $response->getBody()->write('Content!');

        $this->expectOutputString('Content!');

        $this->emitter->emit($response);
    }

    public function testMultipleSetCookieHeadersAreNotReplaced(): void
    {
        $response = (new Response())
            ->withStatus(200)
            ->withAddedHeader('Set-Cookie', 'foo=bar')
            ->withAddedHeader('Set-Cookie', 'bar=baz');

        $this->emitter->emit($response);

        $expectedStack = [
            ['header' => 'Set-Cookie: foo=bar', 'replace' => false, 'status_code' => 200],
            ['header' => 'Set-Cookie: bar=baz', 'replace' => false, 'status_code' => 200],
            ['header' => 'HTTP/1.1 200 OK', 'replace' => true, 'status_code' => 200],
        ];
        self::assertSame($expectedStack, HeaderStack::stack());
    }

    public function testDoesNotLetResponseCodeBeOverriddenByPHP(): void
    {
        $response = (new Response())
            ->withStatus(202)
            ->withAddedHeader('Location', 'http://api.my-service.com/12345678')
            ->withAddedHeader('Content-Type', 'text/plain');

        $this->emitter->emit($response);

        $expectedStack = [
            ['header' => 'Location: http://api.my-service.com/12345678', 'replace' => true, 'status_code' => 202],
            ['header' => 'Content-Type: text/plain', 'replace' => true, 'status_code' => 202],
            ['header' => 'HTTP/1.1 202 Accepted', 'replace' => true, 'status_code' => 202],
        ];
        self::assertSame($expectedStack, HeaderStack::stack());
    }

    public function testEmitterRespectLocationHeader(): void
    {
        $response = (new Response())
            ->withStatus(200)
            ->withAddedHeader('Location', 'http://api.my-service.com/12345678');

        $this->emitter->emit($response);

        $expectedStack = [
            ['header' => 'Location: http://api.my-service.com/12345678', 'replace' => true, 'status_code' => 200],
            ['header' => 'HTTP/1.1 200 OK', 'replace' => true, 'status_code' => 200],
        ];
        self::assertSame($expectedStack, HeaderStack::stack());
    }

    /**
     * Test emitting a no-content response.
     */
    // TODO : faire aussi ce test avec un emitBodyRange !!!!! et pas seulement avec la méthode emitBody !!!!!
    public function testEmitNoContentResponse()
    {
        $response = (new Response())
            ->withHeader('X-testing', 'value')
            ->withStatus(204);
        $response->getBody()->write('It worked');

        $this->expectOutputString('');

        $this->emitter->emit($response);

        $expectedStack = [
            ['header' => 'X-testing: value', 'replace' => true, 'status_code' => 204],
            ['header' => 'HTTP/1.1 204 No Content', 'replace' => true, 'status_code' => 204],
        ];
        self::assertSame($expectedStack, HeaderStack::stack());
    }



    public function testResponseReplacesPreviouslySetHeaders()
    {
        $response = (new Response())
            ->withHeader('X-Foo', 'baz1')
            ->withAddedHeader('X-Foo', 'baz2');

        $this->emitter->emit($response);

        $expectedStack = [
            ['header' => 'X-Foo: baz1', 'replace' => true, 'status_code' => 200],
            ['header' => 'X-Foo: baz2', 'replace' => false, 'status_code' => 200],
            ['header' => 'HTTP/1.1 200 OK', 'replace' => true, 'status_code' => 200],
        ];
        self::assertSame($expectedStack, HeaderStack::stack());
    }

    public function testResponseDoesNotReplacePreviouslySetSetCookieHeaders()
    {
        $response = (new Response())
            ->withHeader('Set-Cookie', 'foo=bar')
            ->withAddedHeader('Set-Cookie', 'bar=baz');

        $this->emitter->emit($response);

        $expectedStack = [
            ['header' => 'Set-Cookie: foo=bar', 'replace' => false, 'status_code' => 200],
            ['header' => 'Set-Cookie: bar=baz', 'replace' => false, 'status_code' => 200],
            ['header' => 'HTTP/1.1 200 OK', 'replace' => true, 'status_code' => 200],
        ];
        self::assertSame($expectedStack, HeaderStack::stack());
    }
}
