<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/Response.php';
require_once __DIR__ . '/../helpers/Validator.php';
require_once __DIR__ . '/../helpers/UUID.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/AuditController.php';

class UserController {

    public static function index(): void {
        AuthMiddleware::requireAnyRole(['admin', 'treasurer', 'board']);
        $pdo = getDbConnection();
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = min(100, max(1, (int)($_GET['per_page'] ?? 20)));
        $offset = ($page - 1) * $perPage;

        $total = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
        $stmt = $pdo->prepare("SELECT id, name, email, role, join_date, is_active, created_at FROM users ORDER BY name ASC LIMIT ? OFFSET ?");
        $stmt->execute([$perPage, $offset]);
        Response::paginated($stmt->fetchAll(), (int)$total, $page, $perPage);
    }

    public static function show(string $id): void {
        AuthMiddleware::requireAnyRole(['admin', 'treasurer', 'board']);
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("SELECT id, name, email, role, join_date, is_active, created_at FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $member = $stmt->fetch();
        if (!$member) Response::error('Member not found', 404);
        Response::success($member);
    }

    public static function store(): void {
        AuthMiddleware::requireRole('admin');
        $input = json_decode(file_get_contents('php://input'), true);

        $v = new Validator();
        $v->required($input['name'] ?? '', 'Name')
           ->required($input['email'] ?? '', 'Email')->email($input['email'] ?? '', 'Email')
           ->required($input['password'] ?? '', 'Password')
           ->inArray($input['role'] ?? '', ['admin','treasurer','organizer','board','member'], 'Role');
        if (!$v->passes()) Response::error('Validation failed', 422, $v->getErrors());

        $pdo = getDbConnection();
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$input['email']]);
        if ($stmt->fetch()) Response::error('Email already exists', 409);

        $id = UUID::v4();
        $hashed = password_hash($input['password'], PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("INSERT INTO users (id, name, email, password, role) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$id, $input['name'], $input['email'], $hashed, $input['role'] ?? 'member']);

        // Audit
        AuditController::log('users', $id, 'create', null, $input, AuthMiddleware::getUserId());

        $stmt = $pdo->prepare("SELECT id, name, email, role, join_date, is_active FROM users WHERE id = ?");
        $stmt->execute([$id]);
        Response::success($stmt->fetch(), 'Member created', 201);
    }

    public static function update(string $id): void {
        AuthMiddleware::requireRole('admin');
        $input = json_decode(file_get_contents('php://input'), true);
        $pdo = getDbConnection();

        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $old = $stmt->fetch();
        if (!$old) Response::error('Member not found', 404);

        $fields = [];
        $params = [];
        if (isset($input['name'])) { $fields[] = 'name = ?'; $params[] = $input['name']; }
        if (isset($input['email'])) {
            $v = new Validator();
            $v->email($input['email'], 'Email');
            if (!$v->passes()) Response::error('Validation failed', 422, $v->getErrors());
            $fields[] = 'email = ?'; $params[] = $input['email'];
        }
        if (isset($input['role'])) {
            $v = new Validator();
            $v->inArray($input['role'], ['admin','treasurer','organizer','board','member'], 'Role');
            if (!$v->passes()) Response::error('Validation failed', 422, $v->getErrors());
            $fields[] = 'role = ?'; $params[] = $input['role'];
        }
        if (isset($input['is_active'])) { $fields[] = 'is_active = ?'; $params[] = $input['is_active'] ? 1 : 0; }
        if (isset($input['password'])) {
            $fields[] = 'password = ?'; $params[] = password_hash($input['password'], PASSWORD_BCRYPT);
        }

        if (empty($fields)) Response::error('No fields to update', 400);
        $params[] = $id;
        $pdo->prepare("UPDATE users SET " . implode(', ', $fields) . " WHERE id = ?")->execute($params);

        $new = array_merge($old, $input);
        AuditController::log('users', $id, 'update', $old, $new, AuthMiddleware::getUserId());

        $stmt = $pdo->prepare("SELECT id, name, email, role, join_date, is_active FROM users WHERE id = ?");
        $stmt->execute([$id]);
        Response::success($stmt->fetch(), 'Member updated');
    }

    public static function destroy(string $id): void {
        AuthMiddleware::requireRole('admin');
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $old = $stmt->fetch();
        if (!$old) Response::error('Member not found', 404);

        $pdo->prepare("UPDATE users SET is_active = 0 WHERE id = ?")->execute([$id]);
        AuditController::log('users', $id, 'delete', $old, ['is_active' => 0], AuthMiddleware::getUserId());
        Response::success(null, 'Member deactivated');
    }
}
