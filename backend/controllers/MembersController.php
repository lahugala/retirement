<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/Response.php';
require_once __DIR__ . '/../helpers/Validator.php';
require_once __DIR__ . '/../helpers/UUID.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/AuditController.php';

class MembersController {

    public static function index(): void {
        AuthMiddleware::requireAnyRole(['admin', 'treasurer', 'board', 'organizer']);
        $pdo = getDbConnection();
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = min(100, max(1, (int)($_GET['per_page'] ?? 20)));
        $offset = ($page - 1) * $perPage;

        $total = $pdo->query("SELECT COUNT(*) FROM members")->fetchColumn();
        $stmt = $pdo->prepare("SELECT * FROM members ORDER BY name ASC LIMIT ? OFFSET ?");
        $stmt->execute([$perPage, $offset]);
        Response::paginated($stmt->fetchAll(), (int)$total, $page, $perPage);
    }

    public static function show(string $id): void {
        AuthMiddleware::requireAnyRole(['admin', 'treasurer', 'board', 'organizer']);
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("SELECT * FROM members WHERE id = ?");
        $stmt->execute([$id]);
        $member = $stmt->fetch();
        if (!$member) Response::error('Member not found', 404);
        Response::success($member);
    }

    public static function store(): void {
        AuthMiddleware::requireAnyRole(['admin', 'treasurer']);
        $input = json_decode(file_get_contents('php://input'), true);

        $v = new Validator();
        $v->required($input['name'] ?? '', 'Name')
           ->required($input['nic'] ?? '', 'NIC')
           ->required($input['computer_no'] ?? '', 'Computer Number');
        if (!$v->passes()) Response::error('Validation failed', 422, $v->getErrors());

        $pdo = getDbConnection();
        $id = UUID::v4();

        $stmt = $pdo->prepare("INSERT INTO members (id, name, nic, service_no, computer_no, retirement_date, status)
                               VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $id,
            $input['name'],
            $input['nic'] ?? null,
            $input['service_no'] ?? null,
            $input['computer_no'] ?? null,
            $input['retirement_date'] ?? null,
            $input['status'] ?? 'active',
        ]);

        AuditController::log('members', $id, 'create', null, $input, AuthMiddleware::getUserId());

        $stmt = $pdo->prepare("SELECT * FROM members WHERE id = ?");
        $stmt->execute([$id]);
        Response::success($stmt->fetch(), 'Member created', 201);
    }

    public static function update(string $id): void {
        AuthMiddleware::requireAnyRole(['admin', 'treasurer']);
        $input = json_decode(file_get_contents('php://input'), true);
        $pdo = getDbConnection();

        $stmt = $pdo->prepare("SELECT * FROM members WHERE id = ?");
        $stmt->execute([$id]);
        $old = $stmt->fetch();
        if (!$old) Response::error('Member not found', 404);

        $fields = [];
        $params = [];
        foreach (['name', 'nic', 'service_no', 'computer_no', 'status'] as $f) {
            if (isset($input[$f])) { $fields[] = "$f = ?"; $params[] = $input[$f]; }
        }
        if (isset($input['retirement_date'])) { $fields[] = 'retirement_date = ?'; $params[] = $input['retirement_date'] ?: null; }

        if (empty($fields)) Response::error('No fields to update', 400);
        $params[] = $id;
        $pdo->prepare("UPDATE members SET " . implode(', ', $fields) . " WHERE id = ?")->execute($params);

        AuditController::log('members', $id, 'update', $old, array_merge($old, $input), AuthMiddleware::getUserId());

        $stmt = $pdo->prepare("SELECT * FROM members WHERE id = ?");
        $stmt->execute([$id]);
        Response::success($stmt->fetch(), 'Member updated');
    }

    public static function destroy(string $id): void {
        AuthMiddleware::requireRole('admin');
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("SELECT * FROM members WHERE id = ?");
        $stmt->execute([$id]);
        $old = $stmt->fetch();
        if (!$old) Response::error('Member not found', 404);

        $pdo->prepare("DELETE FROM members WHERE id = ?")->execute([$id]);
        AuditController::log('members', $id, 'delete', $old, null, AuthMiddleware::getUserId());
        Response::success(null, 'Member deleted');
    }
}
