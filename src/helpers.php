<?php
use ePHP\Foundation\App;

if (!function_exists('env')) {
    function env(string $key, $default = null) {
        return $_ENV[$key] ?? $_SERVER[$key] ?? $default;
    }
}

if (!function_exists('config')) {
    function config(string $key, $default = null) {
        // e.g. config('app.name')
        $parts = explode('.', $key);
        $root = 'config.' . $parts[0];
        $config = App::resolve($root);

        if (!$config) return $default;

        unset($parts[0]);
        foreach ($parts as $segment) {
            if (is_array($config) && array_key_exists($segment, $config)) {
                $config = $config[$segment];
            } else {
                return $default;
            }
        }
        return $config;
    }
}

if (!function_exists('app')) {
    function app(?string $abstract = null) {
        return $abstract ? \ePHP\Foundation\App::resolve($abstract) : new \ePHP\Foundation\App;
    }
}

if (!function_exists('route')) {
    function route(string $name, array $params = []): string {
        return \ePHP\Routing\Router::url($name, $params);
    }
}


if (!function_exists('base_path')) {
    function base_path(string $path = ''): string {
        $basePath = dirname(__DIR__);
        return $path ? $basePath . '/' . ltrim($path, '/') : $basePath;
    }
}