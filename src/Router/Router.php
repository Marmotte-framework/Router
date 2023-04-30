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

use Composer\ClassMapGenerator\ClassMapGenerator;
use Marmotte\Brick\Services\Service;
use Marmotte\Brick\Services\ServiceManager;
use Marmotte\Router\Controller\ErrorResponseFactory;
use Marmotte\Router\Exceptions\RouterException;
use Psr\Http\Message\ResponseInterface;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use RuntimeException;

#[Service]
final class Router
{
    private RouteNode $route_tree;

    public function __construct(
        private readonly RouterConfig         $config,
        private readonly ServiceManager       $service_manager,
        private readonly Emitter              $emitter,
        private readonly ErrorResponseFactory $error_response_factory,
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

    /**
     * Call handler for route $route
     * @throws RouterException
     */
    public function route(string $route): void
    {
        $components = array_filter(explode('/', $route), fn($str) => !empty($str));
        $handler    = $this->route_tree->findHandler($components);

        if ($handler === null) {
            $this->emitter->emit(
                $this->error_response_factory->createError(404)
            );
            return;
        }

        $controller_name = $handler['class'];
        $controller_ref  = new ReflectionClass($controller_name);
        $controller      = $this->initController($controller_ref, $handler['args']);
        if ($controller === null) {
            throw new RouterException(sprintf('Fail to construct class %s', $controller_name));
        }

        $method_ref = $controller_ref->getMethod($handler['method']);
        $args       = $this->getArgsForMethod($method_ref, $handler['args']);
        if ($args === null) {
            throw new RouterException(sprintf('Fail to get args of method %s::%s', $controller_name, $handler['method']));
        }

        $response = $method_ref->invoke($controller, $args);
        if (!$response instanceof ResponseInterface) {
            throw new RouterException(sprintf('Route %s not returns a Response', $route));
        }

        $this->emitter->emit($response);
    }

    /**
     * @param array<string, string> $route_args
     */
    private function initController(ReflectionClass $class, array $route_args): ?object
    {
        $constructor = $class->getConstructor();
        if ($constructor === null) {
            return $class->newInstance();
        }

        $args = $this->getArgsForMethod($constructor, $route_args);
        if ($args === null) {
            return null;
        }

        return $class->newInstance($args);
    }

    /**
     * @param array<string, string> $route_args
     */
    private function getArgsForMethod(ReflectionMethod $method, array $route_args): ?array
    {
        $parameters = $method->getParameters();
        $args       = [];
        foreach ($parameters as $parameter) {
            if ($parameter->hasType()) {
                $type = $parameter->getType();
                assert($type instanceof ReflectionNamedType);
                /** @var class-string $name */
                $name = $type->getName();
                if ($this->service_manager->hasService($name)) {
                    $args[] = $this->service_manager->getService($name);
                    continue;
                }
            }

            if (array_key_exists($parameter->getName(), $route_args)) {
                $args[] = $route_args[$parameter->getName()];
                continue;
            }

            return null;
        }

        return $args;
    }
}
