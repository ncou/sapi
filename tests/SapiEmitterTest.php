<?php

declare(strict_types=1);

// TODO : utiliser cette classe pour ajouter des tests : https://github.com/zendframework/zend-diactoros/blob/master/test/Response/SapiStreamEmitterTest.php
// https://github.com/cakephp/cakephp/blob/master/tests/TestCase/Http/ResponseEmitterTest.php

//https://github.com/laminas/laminas-httphandlerrunner/blob/2.2.x/test/Emitter/SapiStreamEmitterTest.php
//https://github.com/cakephp/cakephp/blob/32e3c532fea8abe2db8b697f07dfddf4dfc134ca/tests/TestCase/Http/ResponseEmitterTest.php

//https://github.com/laminas/laminas-diactoros/blob/2.12.x/src/CallbackStream.php
//https://github.com/cakephp/http/blob/4.x/CallbackStream.php
//https://github.com/phly/psr7examples/blob/master/src/CallbackStream.php

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

    /**
     * Test valid body ranges.
     */
    public function testEmitResponseBodyRange(): void
    {
        $response = (new Response())
            ->withHeader('Content-Type', 'text/plain')
            ->withHeader('Content-Range', 'bytes 1-4/9');
        $response->getBody()->write('It worked');

        ob_start();
        $this->emitter->emit($response);
        $out = ob_get_clean();

        $this->assertSame('t wo', $out);

        $expectedStack = [
            ['header' => 'Content-Type: text/plain', 'replace' => true, 'status_code' => 200],
            ['header' => 'Content-Range: bytes 1-4/9', 'replace' => true, 'status_code' => 200],
            ['header' => 'HTTP/1.1 200 OK', 'replace' => true, 'status_code' => 200],
        ];
        $this->assertEquals($expectedStack, HeaderStack::stack());
    }

    /**
     * Test valid body ranges.
     */
    public function testEmitResponseBodyRangeComplete(): void
    {
        $response = (new Response())
            ->withHeader('Content-Type', 'text/plain')
            ->withHeader('Content-Range', 'bytes 0-20/9');
        $response->getBody()->write('It worked');

        ob_start();
        $this->emitter->emit($response);
        $out = ob_get_clean();

        $this->assertSame('It worked', $out);
    }

    /**
     * Test out of bounds body ranges.
     */
    public function testEmitResponseBodyRangeOverflow(): void
    {
        $response = (new Response())
            ->withHeader('Content-Type', 'text/plain')
            ->withHeader('Content-Range', 'bytes 5-20/9');
        $response->getBody()->write('It worked');

        ob_start();
        $this->emitter->emit($response);
        $out = ob_get_clean();

        $this->assertSame('rked', $out);
    }

    /**
     * Test malformed content-range header
     */
    public function testEmitResponseBodyRangeMalformed(): void
    {
        $response = (new Response())
            ->withHeader('Content-Type', 'text/plain')
            ->withHeader('Content-Range', 'bytes 9-ba/a');
        $response->getBody()->write('It worked');

        ob_start();
        $this->emitter->emit($response);
        $out = ob_get_clean();

        $this->assertSame('It worked', $out);
    }
}
