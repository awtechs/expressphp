<?php
namespace ePHP\Http;

final class Response
{
    public static function json(array|object $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function text(string $body, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: text/plain; charset=utf-8');
        echo $body;
        exit;
    }

    public static function html(string $body, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: text/html; charset=utf-8');
        echo $body;
        exit;
    }

    public static function redirect(string $url, int $status = 302): void
    {
        http_response_code($status);
        header("Location: {$url}");
        exit;
    }
}
