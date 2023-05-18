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
use FastRoute\Dispatcher;
use FastRoute\RouteCollector;
use Marmotte\Brick\Services\Service;
use Marmotte\Brick\Services\ServiceManager;
use Marmotte\Router\Controller\ErrorResponseFactory;
use Marmotte\Router\Exceptions\RouterException;
use Psr\Http\Message\ResponseInterface;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use RuntimeException;
use function FastRoute\simpleDispatcher;

#[Service('router.yml')]
final class Router
{
    private readonly Dispatcher $dispatcher;

    public function __construct(
        private readonly RouterConfig         $config,
        private readonly ServiceManager       $service_manager,
        private readonly Emitter              $emitter,
        private readonly ErrorResponseFactory $error_response_factory,
    ) {
        $this->dispatcher = simpleDispatcher(function (RouteCollector $r) {
            $classmap = ClassMapGenerator::createMap($this->config->project_root . '/' . $this->config->controller_root);

            foreach ($classmap as $symbol => $_path) {
                $ref = new ReflectionClass($symbol);

                $this->getRoutesFromClass($ref, $r);
            }
        });
    }

    private function getRoutesFromClass(ReflectionClass $class, RouteCollector $r): void
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
                    $this->addRouteHandler(
                        $base_route . '/' . $method_route,
                        $class->getName(),
                        $method->getName(),
                        $r
                    );
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
    private function addRouteHandler(string $route, string $class, string $method, RouteCollector $r): void
    {
        if (!class_exists($class) || !method_exists($class, $method)) {
            throw new RouterException(sprintf('class or method don\'t exist: %s::%s', $class, $method));
        }

        $r->addRoute('*', $this->cleanRoute($route), [$class, $method]);
    }

    /**
     * Call handler for route $route
     */
    public function route(string $route): void
    {
        $dispatch = $this->dispatcher->dispatch('*', $this->cleanRoute($route));

        switch ($dispatch[0]) {
            case Dispatcher::NOT_FOUND:
                $response = $this->error_response_factory->createError(404);
                break;
            case Dispatcher::METHOD_NOT_ALLOWED:
                /** @var string[] $methods */
                $methods  = $dispatch[1];
                $response = $this->error_response_factory
                    ->createError(405)
                    ->withHeader('Allow', $methods);
                break;
            case Dispatcher::FOUND:
                /** @var array $handler */
                $handler = $dispatch[1];
                /** @var class-string $class */
                $class = $handler[0];
                /** @var string $method */
                $method = $handler[1];
                /** @var array<string, string> $args */
                $args     = $dispatch[2];
                $response = $this->handle($class, $method, $args);
                break;
            default:
                $response = $this->error_response_factory->createError(500);
        }

        $this->emitter->emit($response);
    }

    /**
     * @param class-string $class
     * @param array<string, string> $route_args
     * @throws RouterException
     */
    private function handle(string $class, string $method, array $route_args): ResponseInterface
    {
        $controller_ref = new ReflectionClass($class);
        $controller     = $this->initController($controller_ref, $route_args);
        if ($controller === null) {
            throw new RouterException(sprintf('Fail to construct class %s', $class));
        }

        $method_ref = $controller_ref->getMethod($method);
        $args       = $this->getArgsForMethod($method_ref, $route_args);
        if ($args === null) {
            throw new RouterException(sprintf('Fail to get args of method %s::%s', $class, $method));
        }

        $response = $method_ref->invoke($controller, ...$args);
        if (!($response instanceof ResponseInterface)) {
            throw new RouterException('Controller not returned a ResponseInterface');
        }

        return $response;
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

        return $class->newInstance(...$args);
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

    private function cleanRoute(string $route): string
    {
        return implode(
            '/',
            array_filter(
                explode('/', $route),
                static fn(string $str) => !empty($str)
            )
        );
    }
}
