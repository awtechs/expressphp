<?php
namespace ePHP\Middleware\Contracts;

use ePHP\Http\Request;

interface MiddlewareInterface
{
    // $next is a callable that continues the request (function(Request): mixed)
    public function handle(Request $request, callable $next);
}
