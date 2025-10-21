<?php
namespace ePHP\Foundation;

use ReflectionClass;
use ReflectionParameter;
use Throwable;

final class App
{
    private static array $bindings = [];
    private static array $singletons = [];
    private static array $instances = [];

    // bind abstract -> concrete (string class or factory callable)
    public static function bind(string $abstract, string|callable $concrete): void
    {
        self::$bindings[$abstract] = $concrete;
    }

    // bind singleton
    public static function singleton(string $abstract, string|callable $concrete): void
    {
        self::$singletons[$abstract] = $concrete;
    }

    public static function instance(string $abstract, object $instance): void
    {
        self::$instances[$abstract] = $instance;
    }

    public static function resolve(string $class)
    {
        // existing instance
        if (isset(self::$instances[$class])) {
            return self::$instances[$class];
        }

        // singleton factory
        if (isset(self::$singletons[$class])) {
            $concrete = self::$singletons[$class];
            $instance = is_callable($concrete) ? $concrete() : new $concrete();
            self::$instances[$class] = $instance;
            return $instance;
        }

        // binding
        if (isset(self::$bindings[$class])) {
            $concrete = self::$bindings[$class];
            return is_callable($concrete) ? $concrete() : new $concrete();
        }

        // auto-resolve via reflection
        if (!class_exists($class)) {
            throw new \RuntimeException("Class {$class} does not exist.");
        }

        $ref = new ReflectionClass($class);
        $ctor = $ref->getConstructor();
        if (!$ctor || $ctor->getNumberOfParameters() === 0) {
            return new $class();
        }

        $args = [];
        foreach ($ctor->getParameters() as $param) {
            $args[] = self::resolveParam($param);
        }

        return $ref->newInstanceArgs($args);
    }

    private static function resolveParam(ReflectionParameter $param)
    {
        $type = $param->getType();
        if ($type && !$type->isBuiltin()) {
            $name = $type->getName();
            return self::resolve($name);
        }

        if ($param->isDefaultValueAvailable()) {
            return $param->getDefaultValue();
        }

        return null;
    }

    // helper for registering dot env into container
    public static function env(string $key, $default = null)
    {
        return $_ENV[$key] ?? $_SERVER[$key] ?? $default;
    }
}
