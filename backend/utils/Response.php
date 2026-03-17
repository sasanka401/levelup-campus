<?php
/**
 * Sends a JSON response and exits.
 * All API responses go through here for consistency.
 */
class Response {

    public static function json(array $data, int $statusCode = 200): void {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function success(string $message, array $data = [], int $code = 200): void {
        self::json(array_merge(['success' => true, 'message' => $message], $data), $code);
    }

    public static function error(string $message, int $code = 400, array $extra = []): void {
        self::json(array_merge(['success' => false, 'message' => $message], $extra), $code);
    }

    public static function notFound(string $message = 'Resource not found.'): void {
        self::error($message, 404);
    }

    public static function unauthorized(string $message = 'Unauthorized. Please log in.'): void {
        self::error($message, 401);
    }

    public static function forbidden(string $message = 'Access denied.'): void {
        self::error($message, 403);
    }

    public static function serverError(string $message = 'Internal server error.'): void {
        self::error($message, 500);
    }
}
