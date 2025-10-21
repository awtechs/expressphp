<?php
namespace ePHP\Routing;

final class Route
{
    public string $method;
    public string $uri;
    public $action;
    public array $middleware = [];
    public ?string $name = null;
    public string $pattern;

    public function __construct(string $method, string $uri, $action)
    {
        $this->method = strtoupper($method);
        $this->uri = '/' . ltrim($uri, '/');
        $this->action = $action;
    }
}
