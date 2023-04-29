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

use Composer\ClassMapGenerator\ClassMapGenerator;
use Marmotte\Brick\Services\Service;
use Marmotte\Router\Exceptions\RouterException;
use ReflectionClass;
use RuntimeException;

#[Service]
final class Router
{
    private RouteNode $route_tree;

    public function __construct(
        private readonly RouterConfig $config,
    ) {
        $this->route_tree = new RouteNode('');

        $classmap = ClassMapGenerator::createMap($this->config->controller_root);

        foreach ($classmap as $symbol => $_path) {
            $ref = new ReflectionClass($symbol);

            $this->getRoutesFromClass($ref);
        }
    }

    private function getRoutesFromClass(ReflectionClass $class): void
    {
        $class_attrs = $class->getAttributes(Route::class);

        $base_route = '';
        if (!empty($class_attrs)) {
            $route_attr = $class_attrs[0]->newInstance();
            $base_route = $route_attr->route;
        }

        foreach ($class->getMethods() as $method) {
            $method_attrs = $method->getAttributes(Route::class);

            if (!empty($method_attrs)) {
                $route_attr   = $method_attrs[0]->newInstance();
                $method_route = $route_attr->route;

                try {
                    $this->addRouteHandler($base_route . '/' . $method_route, $class->getName(), $method->getName());
                } catch (RouterException $e) {
                    throw new RuntimeException(previous: $e);
                }
            }
        }
    }

    // _.-._.-._.-._.-._.-._.-._.-._.-._.-._.-._.-._.-._.-._.-._.-._.-._.-._.-._.-._.-.

    /**
     * @param class-string $class
     * @throws RouterException
     */
    public function addRouteHandler(string $route, string $class, string $method): void
    {
        // Check that $class and $methods exists
        if (!class_exists($class) || !method_exists($class, $method)) {
            throw new RouterException(sprintf('class or method don\'t exist: %s::%s', $class, $method));
        }

        $components = array_filter(explode('/', $route), fn($str) => !empty($str));
        if (empty($components)) {
            throw new RouterException(sprintf('Route %s is not valid', $route));
        }

        $route_node = new RouteNode(array_pop($components));
        if (!$this->route_tree->addSubRoute($components, $route_node)) {
            throw new RouterException(sprintf('Fail to add route %s', $route));
        }
    }
}
