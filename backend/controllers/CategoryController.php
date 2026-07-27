<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/Response.php';
require_once __DIR__ . '/../helpers/Validator.php';
require_once __DIR__ . '/../helpers/UUID.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/AuditController.php';

class CategoryController {

    public static function index(): void {
        AuthMiddleware::authenticate();
        $pdo = getDbConnection();
        $type = $_GET['type'] ?? null;
        if ($type) {
            $stmt = $pdo->prepare("SELECT * FROM categories WHERE type = ? AND is_active = 1 ORDER BY name");
            $stmt->execute([$type]);
        } else {
            $stmt = $pdo->query("SELECT * FROM categories WHERE is_active = 1 ORDER BY type, name");
        }
        Response::success($stmt->fetchAll());
    }

    public static function show(string $id): void {
        AuthMiddleware::authenticate();
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
        $stmt->execute([$id]);
        $cat = $stmt->fetch();
        if (!$cat) Response::error('Category not found', 404);
        Response::success($cat);
    }

    public static function store(): void {
        AuthMiddleware::requireAnyRole(['admin', 'treasurer']);
        $input = json_decode(file_get_contents('php://input'), true);

        $v = new Validator();
        $v->required($input['name'] ?? '', 'Name')
           ->required($input['type'] ?? '', 'Type')
           ->inArray($input['type'] ?? '', ['income', 'expense'], 'Type');
        if (!$v->passes()) Response::error('Validation failed', 422, $v->getErrors());

        $id = UUID::v4();
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("INSERT INTO categories (id, type, name) VALUES (?, ?, ?)");
        $stmt->execute([$id, $input['type'], $input['name']]);

        AuditController::log('categories', $id, 'create', null, $input, AuthMiddleware::getUserId());

        $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
        $stmt->execute([$id]);
        Response::success($stmt->fetch(), 'Category created', 201);
    }

    public static function update(string $id): void {
        AuthMiddleware::requireAnyRole(['admin', 'treasurer']);
        $input = json_decode(file_get_contents('php://input'), true);
        $pdo = getDbConnection();

        $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
        $stmt->execute([$id]);
        $old = $stmt->fetch();
        if (!$old) Response::error('Category not found', 404);

        $fields = [];
        $params = [];
        if (isset($input['name'])) { $fields[] = 'name = ?'; $params[] = $input['name']; }
        if (isset($input['type'])) {
            $v = new Validator();
            $v->inArray($input['type'], ['income', 'expense'], 'Type');
            if (!$v->passes()) Response::error('Validation failed', 422, $v->getErrors());
            $fields[] = 'type = ?'; $params[] = $input['type'];
        }
        if (isset($input['is_active'])) { $fields[] = 'is_active = ?'; $params[] = $input['is_active'] ? 1 : 0; }

        if (empty($fields)) Response::error('No fields to update', 400);
        $params[] = $id;
        $pdo->prepare("UPDATE categories SET " . implode(', ', $fields) . " WHERE id = ?")->execute($params);

        $new = array_merge($old, $input);
        AuditController::log('categories', $id, 'update', $old, $new, AuthMiddleware::getUserId());

        $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
        $stmt->execute([$id]);
        Response::success($stmt->fetch(), 'Category updated');
    }

    public static function destroy(string $id): void {
        AuthMiddleware::requireAnyRole(['admin', 'treasurer']);
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
        $stmt->execute([$id]);
        $old = $stmt->fetch();
        if (!$old) Response::error('Category not found', 404);

        $pdo->prepare("UPDATE categories SET is_active = 0 WHERE id = ?")->execute([$id]);
        AuditController::log('categories', $id, 'delete', $old, ['is_active' => 0], AuthMiddleware::getUserId());
        Response::success(null, 'Category deactivated');
    }
}
