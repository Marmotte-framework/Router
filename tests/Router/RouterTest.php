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

namespace Marmotte\Router\Router;

use Marmotte\Brick\Bricks\BrickLoader;
use Marmotte\Brick\Bricks\BrickManager;
use Marmotte\Brick\Cache\CacheManager;
use Marmotte\Brick\Mode;
use PHPUnit\Framework\TestCase;
use Throwable;

class RouterTest extends TestCase
{
    private static Router $router;

    public static function setUpBeforeClass(): void
    {
        $brick_manager = new BrickManager();
        $brick_loader  = new BrickLoader(
            $brick_manager,
            new CacheManager(mode: Mode::TEST)
        );
        $brick_loader->loadFromDir(__DIR__ . '/../../src');
        $brick_loader->loadBricks();
        $service_manager = $brick_manager->initialize(__DIR__ . '/../Fixtures', __DIR__ . '/../Fixtures');

        self::assertTrue($service_manager->hasService(Router::class));
        self::$router = $service_manager->getService(Router::class);
    }

    /**
     * @dataProvider getTestRouteData
     */
    public function testRoute(string $route, string $result): void
    {
        ob_start();
        try {
            self::$router->route($route);
            $output = ob_get_clean();
        } catch (Throwable $e) {
            ob_end_clean();
            self::fail($e->getMessage());
        }

        self::assertSame($result, $output);
    }

    public static function getTestRouteData(): iterable
    {
        yield 'It can\'t find route /gbleskefe' => [
            'route'  => '/gbleskefe',
            'result' => self::getErrorResponse(404, 'Not Found'),
        ];
        yield 'It can find home route' => [
            'route'  => '/',
            'result' => 'home',
        ];
        yield 'It can find home route 2' => [
            'route'  => '',
            'result' => 'home',
        ];
        yield 'It can find contact route' => [
            'route'  => '/contact',
            'result' => 'contact',
        ];
        yield 'It can find contact route 2' => [
            'route'  => 'contact',
            'result' => 'contact',
        ];
        yield 'It can find about route' => [
            'route'  => '/about',
            'result' => 'about',
        ];
        yield 'It can find about route 2' => [
            'route'  => 'about',
            'result' => 'about',
        ];
        yield 'It can find map route' => [
            'route'  => '/map',
            'result' => 'map',
        ];
        yield 'It can find map route 2' => [
            'route'  => '/map/',
            'result' => 'map',
        ];
        yield 'It can find map route 3' => [
            'route'  => 'map/',
            'result' => 'map',
        ];

        for ($i = 0; $i < 10; $i++) {
            $id = rtrim(strtr(base64_encode(random_bytes(8)), '+/', '-_'), '=');
            yield "It can find blog/{id} route with id $id" => [
                'route'  => "blog/$id",
                'result' => "Article $id",
            ];
        }

        yield 'It can find blog/tag route' => [
            'route'  => 'blog/tag',
            'result' => 'tag',
        ];

        for ($i = 0; $i < 10; $i++) {
            $id = rtrim(strtr(base64_encode(random_bytes(8)), '+/', '-_'), '=');
            yield "It can find blog/{id}/comments route with id $id" => [
                'route'  => "blog/$id/comments",
                'result' => "Article $id comments",
            ];
        }
    }

    private static function getErrorResponse(int $code, string $reason): string
    {
        return "<!DOCTYPE html>
<html lang='en'>
<head>
<title>Error $code</title>
</head>
<body>
<h1>Sorry, there is an error $code</h1>
<h3>$reason</h3>
</body>
</html>
";
    }
}
