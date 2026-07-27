<?php
require_once __DIR__ . '/../helpers/JWT.php';
require_once __DIR__ . '/../helpers/Response.php';

class AuthMiddleware {
    private static ?array $user = null;

    public static function authenticate(): array {
        $token = null;

        // Read from Authorization header
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
        if (preg_match('/Bearer\s+(.+)/i', $authHeader, $matches)) {
            $token = $matches[1];
        }

        if (!$token) {
            Response::error('Authentication required', 401);
        }

        $payload = JWT::decode($token);
        if (!$payload) {
            Response::error('Invalid or expired token', 401);
        }

        self::$user = $payload;
        return $payload;
    }

    public static function getUser(): ?array {
        return self::$user;
    }

    public static function getUserId(): ?string {
        return self::$user['user_id'] ?? null;
    }

    public static function getUserRole(): ?string {
        return self::$user['role'] ?? null;
    }

    public static function requireRole(string ...$roles): void {
        self::authenticate();
        if (!in_array(self::getUserRole(), $roles, true)) {
            Response::error('Insufficient permissions', 403);
        }
    }

    public static function requireAnyRole(array $roles): void {
        self::authenticate();
        if (!in_array(self::getUserRole(), $roles, true)) {
            Response::error('Insufficient permissions', 403);
        }
    }
}
