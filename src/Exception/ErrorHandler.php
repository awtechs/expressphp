<?php

namespace ePHP\Exception;

use ePHP\Foundation\App;
use ePHP\Http\Response;

final class ErrorHandler
{
    public static function register(): void
    {
        set_exception_handler([self::class, 'handle']);
        set_error_handler([self::class, 'errorToException']);
    }

    public static function handle(\Throwable $e): void
    {
        // very small default: return JSON in production-like format
        $isProd = (App::env('APP_ENV', 'production') === 'production');
        $payload = ['error' => 'Server Error'];
        if (!$isProd) {
            $payload['message'] = $e->getMessage();
            $payload['trace'] = array_slice(explode("\n", $e->getTraceAsString()), 0, 10);
        }
        Response::json($payload, 500);
    }

    public static function errorToException($severity, $message, $file = null, $line = null)
    {
        throw new \ErrorException($message, 0, $severity, $file, $line);
    }
}
