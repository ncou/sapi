<?php

namespace Chiron\Sapi;

use Chiron\Sapi\Test\Utils\HeaderStack;

/**
 * Have headers been sent?
 *
 * @param null|string $file
 * @param null|int    $line
 *
 * @return bool false
 */
function headers_sent($file = null, $line = null): bool
{
    return false;
}
/**
 * Emit a header, without creating actual output artifacts.
 *
 * @param string   $string
 * @param bool     $replace
 * @param null|int $statusCode
 */
function header($string, $replace = true, $statusCode = null): void
{
    HeaderStack::push(
        [
            'header'      => $string,
            'replace'     => $replace,
            'status_code' => $statusCode,
        ]
    );
}
