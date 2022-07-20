<?php

declare(strict_types=1);

namespace Chiron\Sapi;

use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Chiron\Sapi\Exception\EmitterException;

// TODO : pour les tests avec un body noSeekabke ou noReadable il faut utiliser la classe :
//https://github.com/cakephp/cakephp/blob/32e3c532fea8abe2db8b697f07dfddf4dfc134ca/src/Http/CallbackStream.php
//https://github.com/laminas/laminas-diactoros/blob/2.12.x/src/CallbackStream.php

//https://github.com/cakephp/http/blob/5.x/ResponseEmitter.php
//https://github.com/laminas/laminas-httphandlerrunner/blob/2.2.x/src/Emitter/SapiStreamEmitter.php#L80

// TODO : il faut aussi enlever le header Content-Type si la réponse à un code 304 ou 204 !!!! https://github.com/cakephp/cakephp/blob/dd9d8d563cb934daf0d564acf25f1b5308fae65a/src/Http/Response.php#L496

//https://github.com/narrowspark/http-emitter/blob/9e61a16408c81e656050c0dfde641f641527a29c/src/SapiEmitter.php

//https://github.com/ventoviro/windwalker-framework/blob/8b1aba30967dd0e6c4374aec0085783c3d0f88b4/src/Http/Output/Output.php
//https://github.com/windwalker-io/framework/blob/master/packages/http/src/Output/StreamOutput.php

//https://github.com/spiral/http/blob/master/src/Emitter/SapiEmitter.php

//https://github.com/zendframework/zend-expressive-router/blob/master/src/Middleware/ImplicitHeadMiddleware.php
//https://github.com/zendframework/zend-expressive-router/blob/e76e6abd277c73268d27d92f7b385991e86488b9/test/Middleware/ImplicitHeadMiddlewareTest.php

//https://github.com/slimphp/Slim/blob/4.x/Slim/ResponseEmitter.php

//https://github.com/yiisoft/yii-web/blob/master/src/SapiEmitter.php#L16
//https://github.com/yiisoft/yii-web/blob/master/src/SapiEmitter.php#L95

// Lever des exception si les infos sont déjà émises (les headers ou si il y a déjà eu un echo de fait !!!) : https://github.com/Furious-PHP/http-runner/blob/master/src/Checker.php#L12   +   https://github.com/Furious-PHP/http-runner/tree/master/src/Exception    +    https://github.com/Furious-PHP/http-runner/blob/master/src/Runner.php#L22
// https://github.com/cakephp/cakephp/blob/master/src/Http/ResponseEmitter.php#L69


// TODO : améliorer la gestion des "no body response" (request = HEAD, code http = 204...etc) en utilisant le code suivant :
//https://github.com/reactphp/http/blob/083c25ef15a314e6634e4ded9172af310c06b854/src/Io/Sender.php#L113
//https://github.com/walkor/http-client/blob/950923c10e7f6dcc16fdb693f32c2e58470eeabe/src/Request.php#L338
//https://github.com/swoole/swoole-src/blob/af6085243387e999548aeb47c0bb5af188114f02/core-tests/deps/llhttp/src/http.c#L115

// TODO : améliorer la gestion du content length : https://github.com/reactphp/http/blob/271dd95975910addd61f51aa35360fa62ad6a1f1/src/Io/StreamingServer.php#L262
// https://github.com/reactphp/http/blob/083c25ef15a314e6634e4ded9172af310c06b854/src/Io/Sender.php#L80
// TODO : gestion du HEAD :   https://github.com/reactphp/http/blob/271dd95975910addd61f51aa35360fa62ad6a1f1/src/Io/StreamingServer.php#L320
// TODO : améliorer la gestion de la date ajoutée automatiquement : https://github.com/reactphp/http/blob/271dd95975910addd61f51aa35360fa62ad6a1f1/src/Io/StreamingServer.php#L254

//https://github.com/symfony/symfony/blob/master/src/Symfony/Component/HttpFoundation/Response.php#L278


// TODO : il devrait pas il y avoir un test sur la méthode request === CONNECT car je pense qu'il n'y a pas non plus de body dans ce cas là !!!!
// TODO : je me demande aussi si ce n'est pas le cas pour les request de type HEAD/TRACE/OPTIONS => https://github.com/amphp/aerys/blob/b47982604a64d8d49f7fc66cdbaf6940d97f3300/lib/Http1Driver.php#L298

/**
 * @psalm-type ParsedRangeType = array{0:string,1:int,2:int,3:'*'|int}
 */
final class SapiEmitter
{
    // TODO : créer une méthode statique dans la classe StatusCode::isEmpty($code) pour avec ce tableau là ? idem en créant une méthode isInformational($code) et isCacheable()...etc, en se basant sur les méthodes de symfony : https://github.com/symfony/symfony/blob/master/src/Symfony/Component/HttpFoundation/Response.php#L1217
    /** @var array list of http code who MUST not have a body */
    //private const NO_BODY_RESPONSE_CODES = [204, 304];

    /** @var int default buffer size (8kb) */
    // TODO : cette valeur est paramétrée dans le fichier http.php.dist et dans la classe HttpConfig il faudrait virer ces infos de ces 2 fichiers car c'est propre au sapi et pas à la configuration générale du module http !!!!
    private const BUFFER_SIZE = 8 * 1024;

    /**
     * Emits a response for a PHP SAPI environment.
     *
     * @param ResponseInterface $response
     *
     * @throws EmitterException if headers have already been sent.
     * @throws EmitterException if output is present in the output buffer.
     */
    public function emit(ResponseInterface $response, bool $withBody): void
    {
        $this->assertNoPreviousOutput();
        $this->emitHeaders($response);
        $this->emitStatusLine($response);

        if ($withBody) {
            $range = $this->parseContentRange($response->getHeaderLine('Content-Range'));
            if (is_array($range)) {
                $this->emitBodyRange($range, $response);
            } else {
                $this->emitBody($response);
            }
        }
    }

    /**
     * Checks to see if content has previously been sent.
     *
     * If either headers have been sent or the output buffer contains content,
     * raises an exception.
     *
     * @throws EmitterException if headers have already been sent.
     * @throws EmitterException if output is present in the output buffer.
     */
    //https://github.com/narrowspark/http-emitter/blob/4f9c37ef20c8506117e1c18600fe183be37e309b/src/AbstractSapiEmitter.php#L47
    //https://github.com/narrowspark/http-emitter/blob/4f9c37ef20c8506117e1c18600fe183be37e309b/tests/AbstractEmitterTest.php#L38
    private function assertNoPreviousOutput(): void
    {
        $file = $line = null;

        if (headers_sent($file, $line)) {
            throw new EmitterException(
                sprintf('Unable to emit response, headers already send in file=%s line=%s.',
                    $file,
                    $line)
            );
        }

        if (ob_get_level() > 0 && ob_get_length() > 0) {
            throw new EmitterException('Unable to emit response, found non closed buffered output.');
        }
    }

    /**
     * Send HTTP Headers.
     *
     * @param ResponseInterface $response
     */
    private function emitHeaders(ResponseInterface $response): void
    {
        $statusCode = $response->getStatusCode();

        foreach ($response->getHeaders() as $name => $values) {
            //$first = strtolower($name) !== 'set-cookie';
            $first = stripos($name, 'Set-Cookie') === 0 ? false : true;
            foreach ($values as $value) {
                $header = sprintf('%s: %s', $name, $value);
                header($header, $first, $statusCode);
                $first = false;
            }
        }
    }

    /**
     * Emit the status line.
     *
     * Emits the status line using the protocol version and status code from
     * the response; if a reason phrase is available, it, too, is emitted.
     *
     * It is important to mention that this method should be called after
     * `emitHeaders()` in order to prevent PHP from changing the status code of
     * the emitted response.
     */
    private function emitStatusLine(ResponseInterface $response): void
    {
        $statusLine = sprintf(
            'HTTP/%s %d %s',
            $response->getProtocolVersion(),
            $response->getStatusCode(),
            $response->getReasonPhrase()
        );

        header($statusLine, true, $response->getStatusCode());
    }

    /**
     * Emit the body content.
     *
     * @param \Psr\Http\Message\ResponseInterface $response The response to emit
     */
    private function emitBody(ResponseInterface $response): void
    {
        // Clear the output buffers.
        //$this->flushOutput();

        $body = $response->getBody();

        if ($body->isSeekable()) {
            $body->rewind();
        }

        if (! $body->isReadable()) {
            echo $body;

            return;
        }

        while (! $body->eof()) {
            echo $body->read(self::BUFFER_SIZE);
        }
    }

    /**
     * Emit a range of the message body.
     *
     * @param array $range The range data to emit
     * @psalm-param ParsedRangeType $range
     *
     * @param \Psr\Http\Message\ResponseInterface $response The response to emit
     *
     * @return void
     */
    private function emitBodyRange(array $range, ResponseInterface $response): void
    {
        [/* $unit */, $first, $last, /* $length */] = $range;

        $body = $response->getBody();

        $length = $last - $first + 1;

        if ($body->isSeekable()) {
            $body->seek($first);

            $first = 0;
        }

        if (! $body->isReadable()) {
            echo substr($body->getContents(), $first, $length);

            return;
        }

        $remaining = $length;

        while ($remaining >= self::BUFFER_SIZE && ! $body->eof()) {
            $contents   = $body->read(self::BUFFER_SIZE);
            $remaining -= strlen($contents);

            echo $contents;
        }

        if ($remaining > 0 && ! $body->eof()) {
            echo $body->read($remaining);
        }
    }

    /**
     * Parse content-range header
     *
     * @see http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.16
     *
     * @param string $header The Content-Range header to parse.
     * @return null|array [unit, first, last, length]; returns null if no
     *     content range or an invalid content range is provided
     *
     * @psalm-return null|ParsedRangeType
     */
    private function parseContentRange(string $header): ?array
    {
        if (! preg_match('/(?P<unit>[\w]+)\s+(?P<first>\d+)-(?P<last>\d+)\/(?P<length>\d+|\*)/', $header, $matches)) {
            return null;
        }

        return [
            $matches['unit'],
            (int) $matches['first'],
            (int) $matches['last'],
            $matches['length'] === '*' ? '*' : (int) $matches['length'],
        ];
    }

    /**
     * Loops through the output buffer, flushing each, before emitting the response.
     *
     * @return void
     */
    // https://github.com/cakephp/http/blob/f6a451876343b3d673a5f0083ebd88c3117885f2/ResponseEmitter.php#L238
    //https://github.com/symfony/symfony/blob/6.2/src/Symfony/Component/HttpFoundation/Response.php#L1257
    /*
    protected function flushOutput(): void
    {
        while (ob_get_level() > 0) {
            ob_end_flush();
        }
    }*/


}
