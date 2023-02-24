<?php

namespace De\Idrinth\Tickets;

use Dotenv\Dotenv;
use FastRoute\Dispatcher;
use FastRoute\RouteCollector;
use ReflectionClass;
use Throwable;
use function FastRoute\simpleDispatcher;

class Application
{
    private $routes=[];
    private DependencyInjector $di;
    private const LIFETIME=86400;
    public function __construct()
    {
        Dotenv::createImmutable(dirname(__DIR__))->load();
        date_default_timezone_set('UTC');
        ini_set('session.gc_maxlifetime', self::LIFETIME);
        session_set_cookie_params(self::LIFETIME, '/', $_ENV['SYSTEM_HOSTNAME'], true, true);
        session_start();
        $_SESSION['_last'] = time();
        $this->di = new DependencyInjector();
    }

    public function register(object $singleton): self
    {
        $this->di->register($singleton);
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
                header('', true, 405);
                echo "405 METHOD NOT ALLOWED";
                break;
            case Dispatcher::FOUND:
                $vars = $routeInfo[2];
                $obj = $this->di->init(new ReflectionClass($routeInfo[1]));
                try {
                    echo $obj->run($_POST, ...array_values($vars));
                } catch (Throwable $t) {
                    header('', true, 500);
                    error_log($t->getFile().':'.$t->getLine().': '.$t->getMessage());
                    error_log($t->getTraceAsString());
                }
                break;
        }
    }
}
