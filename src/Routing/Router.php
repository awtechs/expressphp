<?php
namespace ePHP\Routing;

use ReflectionFunction;
use ReflectionMethod;
use Throwable;
use ePHP\Foundation\App;
use ePHP\Http\Request;
use ePHP\Http\Response;
use ePHP\Exception\ErrorHandler;
use ePHP\Middleware\Contracts\MiddlewareInterface;

final class Router
{
    /** @var Route[] */
    private static array $routes = [];
    private static array $named = [];

    // register route (any method)
    public static function add(string $method, string $uri, $action): void
    {
        $route = new Route($method, $uri, $action);
        // compile pattern immediately (segment-based, avoids trailing-empty capture)
        $route->pattern = self::compilePattern($route->uri);
        self::$routes[] = $route;
    }

    public static function get(string $uri, $action): void { self::add('GET', $uri, $action); }
    public static function post(string $uri, $action): void { self::add('POST', $uri, $action); }
    public static function put(string $uri, $action): void { self::add('PUT', $uri, $action); }
    public static function delete(string $uri, $action): void { self::add('DELETE', $uri, $action); }
    public static function any(string $uri, $action): void { foreach (['GET','POST','PUT','DELETE'] as $m) self::add($m, $uri, $action); }

    // group helper: options: ['prefix' => '/admin', 'middleware' => [...], 'name' => 'admin.']
    public static function group(array $options, callable $routes): void
    {
        $prefix = $options['prefix'] ?? '';
        $middleware = $options['middleware'] ?? [];
        $name = $options['name'] ?? '';

        // temporarily capture current route list length so we can apply group options
        $before = count(self::$routes);
        $routes(); // user registers routes inside closure
        $after = count(self::$routes);

        for ($i = $before; $i < $after; $i++) {
            // prefix uri
            self::$routes[$i]->uri = '/' . trim($prefix, '/') . self::$routes[$i]->uri;
            self::$routes[$i]->pattern = self::compilePattern(self::$routes[$i]->uri);
            // merge middleware
            self::$routes[$i]->middleware = array_merge($middleware, self::$routes[$i]->middleware ?? []);
            // apply name prefix
            if (!empty($name) && self::$routes[$i]->name) {
                self::$routes[$i]->name = $name . self::$routes[$i]->name;
            } elseif (!empty($name)) {
                // leave anonymous unless route sets a name
            }
        }
    }

    // give a route a name so url() can resolve it (call after add)
    public static function name(string $name): void
    {
        $last = array_key_last(self::$routes);
        if ($last !== null) {
            self::$routes[$last]->name = $name;
            self::$named[$name] = self::$routes[$last];
        }
    }

    public static function middleware(array $middleware): void
    {
        $last = array_key_last(self::$routes);
        if ($last !== null) {
            self::$routes[$last]->middleware = array_merge(self::$routes[$last]->middleware ?? [], $middleware);
        }
    }

    private static function compilePattern(string $uri): string
    {
        $uri = rtrim($uri, '/');
        if ($uri === '') $uri = '/';

        // replace {param} and {param?}
        $regex = preg_replace_callback('#\{(\w+)(\?)?\}#', function($m) {
            $name = $m[1];
            if (isset($m[2]) && $m[2] === '?') {
                return '(?:/(?P<' . $name . '>[^/]+))?';
            }
            return '/(?P<' . $name . '>[^/]+)';
        }, $uri);

        // allow optional trailing slash
        return '#^' . $regex . '/?$#';
    }

    // Dispatch entry point
    public static function dispatch(): void
    {
        $request = new Request();
        $method = $request->method();
        $path = $request->path();

        foreach (self::$routes as $route) {
            if ($route->method !== $method) continue;

            if (preg_match($route->pattern, $path, $matches)) {
                // named captures only
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                try {
                    self::handleRoute($route, $request, $params);
                } catch (Throwable $e) {
                    ErrorHandler::handle($e);
                }
            }
        }

        // not found
        Response::json(['error' => 'Route not found'], 404);
    }

    private static function handleRoute(Route $route, Request $request, array $params)
    {
        // build pipeline with middleware
        $middlewareStack = $route->middleware ?? [];

        $core = function(Request $req) use ($route, $params) {
            // resolve and invoke action
            $action = $route->action;

            if (is_callable($action) && (is_array($action) === false || $action instanceof \Closure)) {
                // closure or callable function
                return self::invokeCallable($action, $req, $params);
            }

            if (is_array($action) && count($action) === 2) {
                [$class, $method] = $action;
                $controller = App::resolve($class);
                return self::invokeController($controller, $method, $req, $params);
            }

            throw new \RuntimeException('Invalid route action');
        };

        // wrap middleware (last in array runs first)
        $runner = array_reduce(
            array_reverse($middlewareStack),
            function($next, $mw) {
                return function(Request $req) use ($next, $mw) {
                    // $mw can be string class, callable, or implementing MiddlewareInterface
                    if (is_string($mw) && class_exists($mw)) {
                        $instance = App::resolve($mw);
                        if ($instance instanceof MiddlewareInterface) {
                            return $instance->handle($req, $next);
                        } elseif (is_callable($instance)) {
                            return $instance($req, $next);
                        }
                    } elseif (is_callable($mw)) {
                        return $mw($req, $next);
                    }
                    throw new \RuntimeException('Invalid middleware');
                };
            },
            $core
        );

        // execute pipeline
        $result = $runner($request);

        // auto-handle result types
        if (is_array($result) || is_object($result)) {
            Response::json($result);
        } elseif (is_string($result)) {
            Response::html($result);
        }
        // if controller already printed/echoed, nothing to do
        return;
    }

    private static function invokeCallable(callable $call, Request $req, array $params)
    {
        // attempt to match signature: allow Request injection or named params
        $ref = new ReflectionFunction($call);
        $args = [];
        foreach ($ref->getParameters() as $p) {
            $name = $p->getName();
            $type = $p->getType();
            if ($type && !$type->isBuiltin() && $type->getName() === Request::class) {
                $args[] = $req;
            } elseif (array_key_exists($name, $params)) {
                $args[] = $params[$name];
            } elseif ($p->isDefaultValueAvailable()) {
                $args[] = $p->getDefaultValue();
            } else {
                $args[] = null;
            }
        }
        return $ref->invokeArgs($args);
    }

    private static function invokeController(object $controller, string $method, Request $req, array $params)
    {
        $ref = new ReflectionMethod($controller, $method);
        $args = [];
        foreach ($ref->getParameters() as $p) {
            $name = $p->getName();
            $type = $p->getType();
            if ($type && !$type->isBuiltin() && $type->getName() === Request::class) {
                $args[] = $req;
            } elseif (array_key_exists($name, $params)) {
                $args[] = $params[$name];
            } elseif ($p->isDefaultValueAvailable()) {
                $args[] = $p->getDefaultValue();
            } else {
                $args[] = null;
            }
        }
        return $ref->invokeArgs($controller, $args);
    }

    // named route url generator
    public static function url(string $name, array $params = []): string
    {
        if (!isset(self::$named[$name])) {
            throw new \RuntimeException("No route named {$name}");
        }
        $route = self::$named[$name];
        $uri = $route->uri;
        foreach ($params as $k => $v) {
            $uri = preg_replace('#\{' . $k . '\??\}#', $v, $uri);
        }
        // remove unmatched optional segments
        $uri = preg_replace('#/\{[\w]+\?\}#', '', $uri);
        return $uri === '' ? '/' : $uri;
    }
}
