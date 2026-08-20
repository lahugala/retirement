<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/Response.php';
require_once __DIR__ . '/../helpers/Validator.php';
require_once __DIR__ . '/../helpers/UUID.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/AuditController.php';

class AccountController {

    public static function index(): void {
        AuthMiddleware::authenticate();
        $pdo = getDbConnection();
        $type = $_GET['type'] ?? null;
        if ($type) {
            $stmt = $pdo->prepare("SELECT * FROM accounts WHERE type = ? AND is_active = 1 ORDER BY code");
            $stmt->execute([$type]);
        } else {
            $stmt = $pdo->query("SELECT * FROM accounts WHERE is_active = 1 ORDER BY type, code");
        }
        Response::success($stmt->fetchAll());
    }

    public static function show(string $id): void {
        AuthMiddleware::authenticate();
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("SELECT * FROM accounts WHERE id = ?");
        $stmt->execute([$id]);
        $account = $stmt->fetch();
        if (!$account) Response::error('Account not found', 404);
        Response::success($account);
    }

    public static function store(): void {
        AuthMiddleware::requireAnyRole(['admin', 'treasurer']);
        $input = json_decode(file_get_contents('php://input'), true);

        $v = new Validator();
        $v->required($input['code'] ?? '', 'Code')
           ->required($input['name'] ?? '', 'Name')
           ->required($input['type'] ?? '', 'Type')
           ->inArray($input['type'] ?? '', ['asset','liability','equity','income','expense'], 'Type');
        if (!$v->passes()) Response::error('Validation failed', 422, $v->getErrors());

        $id = UUID::v4();
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("INSERT INTO accounts (id, code, name, type, description) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$id, $input['code'], $input['name'], $input['type'], $input['description'] ?? null]);

        AuditController::log('accounts', $id, 'create', null, $input, AuthMiddleware::getUserId());

        $stmt = $pdo->prepare("SELECT * FROM accounts WHERE id = ?");
        $stmt->execute([$id]);
        Response::success($stmt->fetch(), 'Account created', 201);
    }

    public static function update(string $id): void {
        AuthMiddleware::requireAnyRole(['admin', 'treasurer']);
        $input = json_decode(file_get_contents('php://input'), true);
        $pdo = getDbConnection();

        $stmt = $pdo->prepare("SELECT * FROM accounts WHERE id = ?");
        $stmt->execute([$id]);
        $old = $stmt->fetch();
        if (!$old) Response::error('Account not found', 404);

        $fields = []; $params = [];
        foreach (['code', 'name', 'description'] as $f) {
            if (isset($input[$f])) { $fields[] = "$f = ?"; $params[] = $input[$f]; }
        }
        if (isset($input['type'])) {
            $v = new Validator();
            $v->inArray($input['type'], ['asset','liability','equity','income','expense'], 'Type');
            if (!$v->passes()) Response::error('Validation failed', 422, $v->getErrors());
            $fields[] = 'type = ?'; $params[] = $input['type'];
        }
        if (isset($input['is_active'])) { $fields[] = 'is_active = ?'; $params[] = $input['is_active'] ? 1 : 0; }

        if (empty($fields)) Response::error('No fields to update', 400);
        $params[] = $id;
        $pdo->prepare("UPDATE accounts SET " . implode(', ', $fields) . " WHERE id = ?")->execute($params);

        AuditController::log('accounts', $id, 'update', $old, array_merge($old, $input), AuthMiddleware::getUserId());

        $stmt = $pdo->prepare("SELECT * FROM accounts WHERE id = ?");
        $stmt->execute([$id]);
        Response::success($stmt->fetch(), 'Account updated');
    }

    public static function destroy(string $id): void {
        AuthMiddleware::requireAnyRole(['admin', 'treasurer']);
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("SELECT * FROM accounts WHERE id = ?");
        $stmt->execute([$id]);
        $old = $stmt->fetch();
        if (!$old) Response::error('Account not found', 404);

        $pdo->prepare("UPDATE accounts SET is_active = 0 WHERE id = ?")->execute([$id]);
        AuditController::log('accounts', $id, 'delete', $old, ['is_active' => 0], AuthMiddleware::getUserId());
        Response::success(null, 'Account deactivated');
    }
}
