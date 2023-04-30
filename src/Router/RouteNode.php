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

final class RouteNode
{
    private const VARIABLE_ROUTE = '/^\/\{.*}$/';

    public readonly bool $is_variable;
    /**
     * @var RouteNode[]
     */
    public array $variable_sub_routes;

    /**
     * @param array<string, RouteNode> $sub_routes
     * @param class-string|null $class
     */
    public function __construct(
        public readonly string $route,
        public array           $sub_routes = [],
        public ?string         $class = null,
        public ?string         $method = null,
    ) {
        $this->is_variable         = preg_match(self::VARIABLE_ROUTE, $this->route) === 1;
        $this->variable_sub_routes = [];
    }

    /**
     * @param string[] $path
     */
    public function addSubRoute(array $path, RouteNode $new_route): bool
    {
        if (empty($path)) {
            if ($new_route->is_variable) {
                $this->variable_sub_routes[] = $new_route;
                return true;
            } else if (!array_key_exists($new_route->route, $this->sub_routes)) {
                $this->sub_routes[$new_route->route] = $new_route;
                return true;
            }
            return false;
        }

        $route = array_shift($path);

        if (preg_match(self::VARIABLE_ROUTE, $route) === 1) {
            foreach ($this->variable_sub_routes as $sub_route) {
                if ($sub_route->addSubRoute($path, $new_route)) {
                    return true;
                }
            }

            return false;
        } else {
            if (array_key_exists($route, $this->sub_routes)) {
                return $this->sub_routes[$route]->addSubRoute($path, $new_route);
            } else {
                $route_node               = new RouteNode($route);
                $this->sub_routes[$route] = $route_node;

                return $route_node->addSubRoute($path, $new_route);
            }
        }
    }

    /**
     * @param string[] $routes
     * @param array<string, string> $args
     * @return ?array{
     *     class: class-string,
     *     method: string,
     *     args: array<string, string>
     * }
     */
    public function findHandler(array $routes, array $args = []): ?array
    {
        if (empty($routes)) {
            if ($this->class !== null && $this->method !== null) {
                return [
                    'class'  => $this->class,
                    'method' => $this->method,
                    'args'   => $args,
                ];
            } else {
                return null;
            }
        }

        $route = array_shift($routes);

        // Look if static routes match
        if (array_key_exists($route, $this->sub_routes)) {
            return $this->sub_routes[$route]->findHandler($routes, $args);
        }

        // Else, look if variable routes match
        foreach ($this->variable_sub_routes as $sub_route) {
            $route_name = substr($sub_route->route, 2, strlen($sub_route->route) - 3);
            if (($handler = $sub_route->findHandler($routes, [
                    ...$args,
                    $route_name => substr($route, 1),
                ])) !== null) {
                return $handler;
            }
        }

        // No route found
        return null;
    }

    public function dump(string $base = ''): string
    {
        $base   = $base . $this->route;
        $result = $base . ' -> ' . ($this->class ?? 'null') . '::' . ($this->method ?? 'null') . "\n";

        foreach ($this->variable_sub_routes as $sub_route) {
            $result .= $sub_route->dump($base);
        }
        foreach ($this->sub_routes as $sub_route) {
            $result .= $sub_route->dump($base);
        }

        return $result;
    }
}
