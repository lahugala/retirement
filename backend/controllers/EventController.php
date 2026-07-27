<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/Response.php';
require_once __DIR__ . '/../helpers/Validator.php';
require_once __DIR__ . '/../helpers/UUID.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/AuditController.php';

class EventController {

    public static function index(): void {
        AuthMiddleware::authenticate();
        $pdo = getDbConnection();
        $role = AuthMiddleware::getUserRole();
        $userId = AuthMiddleware::getUserId();

        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = min(100, max(1, (int)($_GET['per_page'] ?? 50)));
        $offset = ($page - 1) * $perPage;
        $status = $_GET['status'] ?? null;
        $year = $_GET['year'] ?? null;
        $quarter = $_GET['quarter'] ?? null;

        $where = '';
        $params = [];

        if ($role === 'organizer') {
            $where = "WHERE e.organizer_id = ?";
            $params[] = $userId;
        } else {
            $where = "WHERE 1=1";
        }

        if ($status) { $where .= " AND e.status = ?"; $params[] = $status; }
        if ($year)   { $where .= " AND e.year = ?";    $params[] = (int)$year; }
        if ($quarter) { $where .= " AND e.quarter = ?"; $params[] = (int)$quarter; }

        $countSql = "SELECT COUNT(*) FROM events e $where";
        $total = $pdo->prepare($countSql);
        $total->execute($params);
        $totalCount = $total->fetchColumn();

        $sql = "SELECT e.*, m.name AS organizer_name,
                       COALESCE((SELECT SUM(amount) FROM transactions t WHERE t.event_id = e.id AND t.type = 'expense' AND t.is_deleted = 0 AND t.status IN ('approved','pending_approval')), 0) AS total_expense
                FROM events e
                LEFT JOIN users m ON e.organizer_id = m.id
                $where
                ORDER BY e.year DESC, e.quarter ASC, e.created_at DESC
                LIMIT ? OFFSET ?";
        $params[] = $perPage;
        $params[] = $offset;
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        Response::paginated($stmt->fetchAll(), (int)$totalCount, $page, $perPage);
    }

    public static function show(string $id): void {
        AuthMiddleware::authenticate();
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("SELECT e.*, m.name AS organizer_name
                               FROM events e
                               LEFT JOIN users m ON e.organizer_id = m.id
                               WHERE e.id = ?");
        $stmt->execute([$id]);
        $event = $stmt->fetch();
        if (!$event) Response::error('Event not found', 404);

        // Get budget summary
        $budgetStmt = $pdo->prepare("SELECT b.*, c.name AS category_name, c.type AS category_type
                                     FROM budgets b
                                     JOIN categories c ON b.category_id = c.id
                                     WHERE b.event_id = ?");
        $budgetStmt->execute([$id]);
        $event['budgets'] = $budgetStmt->fetchAll();

        // Get transaction summary
        $txnStmt = $pdo->prepare("SELECT t.*, c.name AS category_name, m.name AS created_by_name
                                  FROM transactions t
                                  JOIN categories c ON t.category_id = c.id
                                  LEFT JOIN users m ON t.created_by = m.id
                                  WHERE t.event_id = ? AND t.is_deleted = 0
                                  ORDER BY t.transaction_date DESC");
        $txnStmt->execute([$id]);
        $event['transactions'] = $txnStmt->fetchAll();

        Response::success($event);
    }

    public static function store(): void {
        AuthMiddleware::requireAnyRole(['admin', 'treasurer', 'organizer']);
        $input = json_decode(file_get_contents('php://input'), true);

        $v = new Validator();
        $v->required($input['name'] ?? '', 'Name');
        if (!$v->passes()) Response::error('Validation failed', 422, $v->getErrors());

        $id = UUID::v4();
        $pdo = getDbConnection();

        // Auto-set name if not provided
        $name = $input['name'] ?? '';
        $q = $input['quarter'] ?? null;
        $y = $input['year'] ?? date('Y');
        if (!$name && $q) {
            $qLabels = ['', 'Jan-Apr', 'May-Aug', 'Sep-Dec'];
            $name = "P{$q} {$qLabels[$q]} $y";
        }

        $stmt = $pdo->prepare("INSERT INTO events (id, name, retiree_name, event_date, quarter, year, budget_allocated, status, organizer_id, notes)
                               VALUES (?, ?, ?, ?, ?, ?, ?, 'planned', ?, ?)");
        $stmt->execute([
            $id,
            $name,
            $input['retiree_name'] ?? null,
            $input['event_date'] ?? null,
            $q,
            $y,
            $input['budget_allocated'] ?? 0,
            AuthMiddleware::getUserId(),
            $input['notes'] ?? null,
        ]);

        AuditController::log('events', $id, 'create', null, $input, AuthMiddleware::getUserId());

        $stmt = $pdo->prepare("SELECT * FROM events WHERE id = ?");
        $stmt->execute([$id]);
        Response::success($stmt->fetch(), 'Event created', 201);
    }

    public static function update(string $id): void {
        AuthMiddleware::authenticate();
        $input = json_decode(file_get_contents('php://input'), true);
        $pdo = getDbConnection();
        $userId = AuthMiddleware::getUserId();
        $role = AuthMiddleware::getUserRole();

        $stmt = $pdo->prepare("SELECT * FROM events WHERE id = ?");
        $stmt->execute([$id]);
        $old = $stmt->fetch();
        if (!$old) Response::error('Event not found', 404);

        // Only organizer, treasurer, admin can update
        if ($role === 'organizer' && $old['organizer_id'] !== $userId) {
            Response::error('You can only edit your own events', 403);
        }
        if (!in_array($role, ['admin', 'treasurer', 'organizer'])) {
            Response::error('Insufficient permissions', 403);
        }

        // Block edits on completed or cancelled events
        if (in_array($old['status'], ['completed', 'cancelled'])) {
            Response::error('Cannot edit a ' . $old['status'] . ' event', 403);
        }

        $fields = [];
        $params = [];
        foreach (['name', 'retiree_name', 'event_date', 'quarter', 'year', 'budget_allocated', 'notes'] as $f) {
            if (array_key_exists($f, $input)) { $fields[] = "$f = ?"; $params[] = $input[$f]; }
        }

        if (empty($fields)) Response::error('No fields to update', 400);
        $params[] = $id;
        $pdo->prepare("UPDATE events SET " . implode(', ', $fields) . " WHERE id = ?")->execute($params);

        $new = array_merge($old, $input);
        AuditController::log('events', $id, 'update', $old, $new, $userId);

        $stmt = $pdo->prepare("SELECT * FROM events WHERE id = ?");
        $stmt->execute([$id]);
        Response::success($stmt->fetch(), 'Event updated');
    }

    public static function updateStatus(string $id): void {
        AuthMiddleware::authenticate();
        $input = json_decode(file_get_contents('php://input'), true);
        $status = $input['status'] ?? '';
        $userId = AuthMiddleware::getUserId();
        $role = AuthMiddleware::getUserRole();

        $v = new Validator();
        $v->required($status, 'Status')->inArray($status, ['planned','active','completed','cancelled'], 'Status');
        if (!$v->passes()) Response::error('Validation failed', 422, $v->getErrors());

        $pdo = getDbConnection();
        $stmt = $pdo->prepare("SELECT * FROM events WHERE id = ?");
        $stmt->execute([$id]);
        $old = $stmt->fetch();
        if (!$old) Response::error('Event not found', 404);

        if ($role === 'organizer' && $old['organizer_id'] !== $userId) {
            Response::error('You can only change status of your own events', 403);
        }

        $pdo->prepare("UPDATE events SET status = ? WHERE id = ?")->execute([$status, $id]);
        AuditController::log('events', $id, 'status_change', $old, array_merge($old, ['status' => $status]), $userId);
        Response::success(['id' => $id, 'status' => $status], 'Event status updated');
    }

    public static function generateQuarters(): void {
        AuthMiddleware::requireAnyRole(['admin', 'treasurer']);
        $input = json_decode(file_get_contents('php://input'), true);
        $year = (int)($input['year'] ?? date('Y'));
        $organizerId = $input['organizer_id'] ?? AuthMiddleware::getUserId();
        $periods = $input['periods'] ?? [
            ['quarter' => 1, 'label' => 'Jan-Apr'],
            ['quarter' => 2, 'label' => 'May-Aug'],
            ['quarter' => 3, 'label' => 'Sep-Dec'],
        ];

        $pdo = getDbConnection();
        $created = [];

        foreach ($periods as $p) {
            $q = (string)$p['quarter'];
            $label = $p['label'];
            $stmt = $pdo->prepare("SELECT id FROM events WHERE quarter = ? AND year = ?");
            $stmt->execute([$q, $year]);
            if ($stmt->fetch()) continue;

            $id = UUID::v4();
            $name = $p['name'] ?? $input['name_pattern'] ?? "P{$q} {$label} $year";
            // Replace variables in name pattern if present
            $name = str_replace(['{quarter}', '{label}', '{year}'], [$q, $label, $year], $name);
            $pdo->prepare("INSERT INTO events (id, name, quarter, year, status, organizer_id)
                           VALUES (?, ?, ?, ?, 'planned', ?)")->execute([$id, $name, $q, $year, $organizerId]);
            AuditController::log('events', $id, 'create', null, ['name' => $name, 'quarter' => $q, 'year' => $year], AuthMiddleware::getUserId());
            $created[] = ['id' => $id, 'name' => $name, 'quarter' => (int)$q, 'year' => $year];
        }

        Response::success($created, 'Periods generated', 201);
    }

    public static function destroy(string $id): void {
        AuthMiddleware::requireAnyRole(['admin', 'treasurer']);
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("SELECT * FROM events WHERE id = ?");
        $stmt->execute([$id]);
        $old = $stmt->fetch();
        if (!$old) Response::error('Event not found', 404);

        $pdo->prepare("UPDATE events SET status = 'cancelled' WHERE id = ?")->execute([$id]);
        AuditController::log('events', $id, 'cancel', $old, ['status' => 'cancelled'], AuthMiddleware::getUserId());
        Response::success(null, 'Event cancelled');
    }
}
