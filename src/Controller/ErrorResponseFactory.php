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

namespace Marmotte\Router\Controller;

use Marmotte\Brick\Services\Service;
use Marmotte\Http\Response\ResponseFactory;
use Marmotte\Http\Stream\StreamFactory;
use Psr\Http\Message\ResponseInterface;

#[Service]
final class ErrorResponseFactory
{
    public function __construct(
        private readonly ResponseFactory $response_factory,
        private readonly StreamFactory   $stream_factory,
    ) {
    }

    public function createError(int $code, string $reason = ''): ResponseInterface
    {
        $response = $this->response_factory->createResponse($code, $reason);

        ob_start();

        echo "<!DOCTYPE html>
<html lang='en'>
<head>
<title>Error $code</title>
</head>
<body>
<h1>Sorry, there is an error $code</h1>
<h3>{$response->getReasonPhrase()}</h3>
</body>
</html>
";

        $output = ob_get_clean();

        return $response->withBody($this->stream_factory->createStream($output));
    }
}
