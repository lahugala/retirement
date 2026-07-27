<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/JWT.php';
require_once __DIR__ . '/../helpers/Response.php';
require_once __DIR__ . '/../helpers/Validator.php';
require_once __DIR__ . '/../helpers/UUID.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';

class AuthController {

    public static function login(): void {
        $input = json_decode(file_get_contents('php://input'), true);
        $email = trim($input['email'] ?? '');
        $password = $input['password'] ?? '';

        $v = new Validator();
        $v->required($email, 'Email')->email($email, 'Email');
        $v->required($password, 'Password');
        if (!$v->passes()) Response::error('Validation failed', 422, $v->getErrors());

        $pdo = getDbConnection();
        $stmt = $pdo->prepare("SELECT id, name, email, password, role, is_active FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $member = $stmt->fetch();

        if (!$member || !password_verify($password, $member['password'])) {
            Response::error('Invalid email or password', 401);
        }

        if (!$member['is_active']) Response::error('Account is disabled', 403);

        $token = JWT::encode([
            'user_id' => $member['id'],
            'email'   => $member['email'],
            'role'    => $member['role'],
            'name'    => $member['name'],
        ]);

        Response::success([
            'token' => $token,
            'user'  => [
                'id'    => $member['id'],
                'name'  => $member['name'],
                'email' => $member['email'],
                'role'  => $member['role'],
            ],
        ], 'Login successful');
    }

    public static function register(): void {
        $input = json_decode(file_get_contents('php://input'), true);
        $name = trim($input['name'] ?? '');
        $email = trim($input['email'] ?? '');
        $password = $input['password'] ?? '';

        $v = new Validator();
        $v->required($name, 'Name')
           ->required($email, 'Email')->email($email, 'Email')
           ->required($password, 'Password');
        if (!$v->passes()) Response::error('Validation failed', 422, $v->getErrors());

        $pdo = getDbConnection();
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) Response::error('Email already registered', 409);

        $id = UUID::v4();
        $hashed = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("INSERT INTO users (id, name, email, password, role) VALUES (?, ?, ?, ?, 'member')");
        $stmt->execute([$id, $name, $email, $hashed]);

        $token = JWT::encode([
            'user_id' => $id,
            'email'   => $email,
            'role'    => 'member',
            'name'    => $name,
        ]);

        Response::success([
            'token' => $token,
            'user'  => ['id' => $id, 'name' => $name, 'email' => $email, 'role' => 'member'],
        ], 'Registration successful', 201);
    }

    public static function me(): void {
        AuthMiddleware::authenticate();
        $userId = AuthMiddleware::getUserId();
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("SELECT id, name, email, role, join_date, is_active FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $member = $stmt->fetch();
        if (!$member) Response::error('User not found', 404);
        Response::success($member);
    }
}
