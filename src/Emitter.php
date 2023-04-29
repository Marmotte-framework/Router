<?php
/**
 * MIT License
 *
 * Copyright (c) 2023-Present Kevin Traini
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */

declare(strict_types=1);

namespace Marmotte\Router;

use Marmotte\Brick\Services\Service;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

#[Service]
final class Emitter
{
    public function __construct()
    {
    }

    public function emit(ResponseInterface $response): void
    {
        $filename = null;
        $line     = null;
        if (headers_sent($filename, $line)) {
            throw new RuntimeException(sprintf('Headers are already sent here : %s:l%d', $filename, $line));
        }

        $this->emitHeaders($response);
        $this->emitStatus($response);
        $this->emitBody($response);
    }

    private function emitHeaders(ResponseInterface $response): void
    {
        $status = $response->getStatusCode();

        foreach ($response->getHeaders() as $header => $values) {
            if (!is_string($header))
                continue;
            $header_name = ucwords($header, '-');
            $first       = $header_name !== 'Set-Cookie';
            foreach ($values as $value) {
                header(sprintf(
                    '%s: %s',
                    $header_name,
                    $value
                ), $first, $status);
                $first = false;
            }
        }
    }

    private function emitStatus(ResponseInterface $response): void
    {
        $reason = $response->getReasonPhrase();
        $status = $response->getStatusCode();
        header(sprintf('HTTP/%s %d%s',
            $response->getProtocolVersion(),
            $response->getStatusCode(),
            $reason ? ' ' . $reason : ''
        ), true, $status);
    }

    private function emitBody(ResponseInterface $response): void
    {
        echo $response->getBody();
    }
}
