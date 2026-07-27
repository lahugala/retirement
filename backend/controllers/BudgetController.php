<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/Response.php';
require_once __DIR__ . '/../helpers/Validator.php';
require_once __DIR__ . '/../helpers/UUID.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/AuditController.php';

class BudgetController {

    public static function index(string $eventId): void {
        AuthMiddleware::authenticate();
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("SELECT b.*, c.name AS category_name, c.type AS category_type
                               FROM budgets b
                               JOIN categories c ON b.category_id = c.id
                               WHERE b.event_id = ?
                               ORDER BY c.name");
        $stmt->execute([$eventId]);
        Response::success($stmt->fetchAll());
    }

    public static function store(string $eventId): void {
        AuthMiddleware::requireAnyRole(['admin', 'treasurer', 'organizer']);
        $input = json_decode(file_get_contents('php://input'), true);

        $v = new Validator();
        $v->required($input['category_id'] ?? '', 'Category ID')
           ->uuid($input['category_id'] ?? '', 'Category ID')
           ->required($input['planned_amount'] ?? '', 'Planned amount')
           ->numeric($input['planned_amount'] ?? '', 'Planned amount')
           ->min($input['planned_amount'] ?? 0, 0, 'Planned amount');
        if (!$v->passes()) Response::error('Validation failed', 422, $v->getErrors());

        $pdo = getDbConnection();
        // Verify event exists
        $stmt = $pdo->prepare("SELECT id FROM events WHERE id = ?");
        $stmt->execute([$eventId]);
        if (!$stmt->fetch()) Response::error('Event not found', 404);

        // Check duplicate category for this event
        $stmt = $pdo->prepare("SELECT id FROM budgets WHERE event_id = ? AND category_id = ?");
        $stmt->execute([$eventId, $input['category_id']]);
        if ($stmt->fetch()) Response::error('Budget for this category already exists for this event', 409);

        $id = UUID::v4();
        $stmt = $pdo->prepare("INSERT INTO budgets (id, event_id, category_id, planned_amount) VALUES (?, ?, ?, ?)");
        $stmt->execute([$id, $eventId, $input['category_id'], $input['planned_amount']]);

        // Update event total budget
        $pdo->prepare("UPDATE events SET budget_allocated = (SELECT COALESCE(SUM(planned_amount), 0) FROM budgets WHERE event_id = ?) WHERE id = ?")
            ->execute([$eventId, $eventId]);

        AuditController::log('budgets', $id, 'create', null, $input, AuthMiddleware::getUserId());

        $stmt = $pdo->prepare("SELECT b.*, c.name AS category_name FROM budgets b JOIN categories c ON b.category_id = c.id WHERE b.id = ?");
        $stmt->execute([$id]);
        Response::success($stmt->fetch(), 'Budget line created', 201);
    }

    public static function update(string $id): void {
        AuthMiddleware::requireAnyRole(['admin', 'treasurer', 'organizer']);
        $input = json_decode(file_get_contents('php://input'), true);
        $pdo = getDbConnection();

        $stmt = $pdo->prepare("SELECT * FROM budgets WHERE id = ?");
        $stmt->execute([$id]);
        $old = $stmt->fetch();
        if (!$old) Response::error('Budget not found', 404);

        $fields = [];
        $params = [];
        if (isset($input['planned_amount'])) {
            $v = new Validator();
            $v->numeric($input['planned_amount'], 'Planned amount')->min($input['planned_amount'], 0, 'Planned amount');
            if (!$v->passes()) Response::error('Validation failed', 422, $v->getErrors());
            $fields[] = 'planned_amount = ?'; $params[] = $input['planned_amount'];
        }
        if (isset($input['category_id'])) {
            $fields[] = 'category_id = ?'; $params[] = $input['category_id'];
        }

        if (empty($fields)) Response::error('No fields to update', 400);
        $params[] = $id;
        $pdo->prepare("UPDATE budgets SET " . implode(', ', $fields) . " WHERE id = ?")->execute($params);

        // Update event total budget
        $pdo->prepare("UPDATE events SET budget_allocated = (SELECT COALESCE(SUM(planned_amount), 0) FROM budgets WHERE event_id = ?) WHERE id = ?")
            ->execute([$old['event_id'], $old['event_id']]);

        AuditController::log('budgets', $id, 'update', $old, array_merge($old, $input), AuthMiddleware::getUserId());

        $stmt = $pdo->prepare("SELECT b.*, c.name AS category_name FROM budgets b JOIN categories c ON b.category_id = c.id WHERE b.id = ?");
        $stmt->execute([$id]);
        Response::success($stmt->fetch(), 'Budget updated');
    }

    public static function destroy(string $id): void {
        AuthMiddleware::requireAnyRole(['admin', 'treasurer']);
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("SELECT * FROM budgets WHERE id = ?");
        $stmt->execute([$id]);
        $budget = $stmt->fetch();
        if (!$budget) Response::error('Budget not found', 404);

        $pdo->prepare("DELETE FROM budgets WHERE id = ?")->execute([$id]);

        $pdo->prepare("UPDATE events SET budget_allocated = (SELECT COALESCE(SUM(planned_amount), 0) FROM budgets WHERE event_id = ?) WHERE id = ?")
            ->execute([$budget['event_id'], $budget['event_id']]);

        AuditController::log('budgets', $id, 'delete', $budget, null, AuthMiddleware::getUserId());
        Response::success(null, 'Budget line deleted');
    }
}
