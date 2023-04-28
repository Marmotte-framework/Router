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

final class RouteNode
{
    /**
     * @param array<string, RouteNode> $sub_routes
     * @param class-string|null        $class
     */
    public function __construct(
        public readonly string $route,
        public array           $sub_routes = [],
        public ?string         $class = null,
        public ?string         $method = null,
    ) {
    }

    public function addSubRoute(RouteNode $sub_route): bool
    {
        if ($this->hasSubRoute($sub_route->route)) {
            return false;
        }

        $this->sub_routes[$sub_route->route] = $sub_route;

        return true;
    }

    public function hasSubRoute(string $route): bool
    {
        return array_key_exists($route, $this->sub_routes);
    }

    public function getSubRoute(string $route): ?RouteNode
    {
        return $this->sub_routes[$route] ?? null;
    }

    public function hasHandler(): bool
    {
        return $this->class !== null && $this->method !== null;
    }

    /**
     * @return ?array{class-string, string}
     */
    public function getHandler(): ?array
    {
        if ($this->class !== null && $this->method !== null) {
            return [$this->class, $this->method];
        }

        return null;
    }
}
