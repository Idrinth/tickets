<?php

namespace De\Idrinth\Tickets;

use Dotenv\Dotenv;
use FastRoute\Dispatcher;
use FastRoute\RouteCollector;
use ReflectionClass;
use ReflectionMethod;
use Throwable;
use function FastRoute\simpleDispatcher;

class Application
{
    private $routes=[];
    private $singletons=[];
    private const LIFETIME=86400;
    public function __construct()
    {
        Dotenv::createImmutable(dirname(__DIR__))->load();
        date_default_timezone_set('UTC');
        ini_set('session.gc_maxlifetime', self::LIFETIME);
        session_set_cookie_params(self::LIFETIME, '/', 'tickets.idrinth.de', true, true);
        session_start();
    }

    public function register(object $singleton): self
    {
        $rf = new ReflectionClass($singleton);
        $this->singletons[$rf->getName()] = $singleton;
        while ($rf = $rf->getParentClass()) {
            $this->singletons[$rf->getName()] = $singleton;
        }
        return $this;
    }

    public function get(string $path, string $class): self
    {
        return $this->add('GET', $path, $class);
    }

    public function post(string $path, string $class): self
    {
        return $this->add('POST', $path, $class);
    }
    private function add(string $method, string $path, string $class): self
    {
        $this->routes[$path] = $this->routes[$path] ?? [];
        $this->routes[$path][$method] = $class;
        return $this;
    }
    private function init(ReflectionClass $class): object
    {
        $args = [];
        $constructor = $class->getConstructor();
        if ($constructor instanceof ReflectionMethod) {
            foreach ($constructor->getParameters() as $parameter) {
                $args[] = $this->singletons[$parameter->getType()->getName()] ?? $this->init($parameter->getClass());
            }
        }
        $handler = $class->name;
        return new $handler(...$args);
    }
    public function run(): void
    {
        $dispatcher = simpleDispatcher(function(RouteCollector $r) {
            foreach ($this->routes as $path => $data) {
                foreach($data as $method => $func) {
                    $r->addRoute($method, $path, $func);
                }
            }
        });
        $httpMethod = $_SERVER['REQUEST_METHOD'];
        $uri = $_SERVER['REQUEST_URI'];
        if (false !== $pos = strpos($uri, '?')) {
            $uri = substr($uri, 0, $pos);
        }
        $uri = rawurldecode($uri);

        $routeInfo = $dispatcher->dispatch($httpMethod, $uri);
        switch ($routeInfo[0]) {
            case Dispatcher::NOT_FOUND:
                header('', true, 404);
                echo "404 NOT FOUND";
                break;
            case Dispatcher::METHOD_NOT_ALLOWED:
                $allowedMethods = $routeInfo[1];
                header('', true, 405);
                echo "405 METHOD NOT ALLOWED";
                break;
            case Dispatcher::FOUND:
                $vars = $routeInfo[2];
                $obj = $this->init(new ReflectionClass($routeInfo[1]));
                try {
                    $obj->run($_POST, ...array_values($vars));
                } catch (Throwable $t) {
                    header('', true, 500);
                    echo "Failed with {$t->getMessage()}";
                }
                break;
        }
    }
}
