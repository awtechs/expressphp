<?php
namespace ePHP\Foundation;

require_once __DIR__ . '/../../../vendor/autoload.php';
use Dotenv\Dotenv;
use ePHP\Exception\ErrorHandler;

final class Bootstrap
{
    public static function init(string $basePath): void
    {
        // Load .env
        $envPath = $basePath . '/.env';
        if (file_exists($envPath)) {
            $dotenv = Dotenv::createImmutable($basePath);
            $dotenv->safeLoad();
        }

        // Register error handler
        ErrorHandler::register();

        // Load configuration files
        self::loadConfigs($basePath . '/config');

        // Load routes
        self::loadRoutes($basePath . '/routes');
    }

    private static function loadConfigs(string $path): void
    {
        if (!is_dir($path)) return;

        foreach (glob("{$path}/*.php") as $file) {
            $name = basename($file, '.php');
            App::instance("config.{$name}", require $file);
        }
    }

    private static function loadRoutes(string $path): void
    {
        if (!is_dir($path)) return;

        foreach (glob("{$path}/*.php") as $file) {
            require_once $file;
        }
    }
}
