<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/Response.php';
require_once __DIR__ . '/../helpers/Validator.php';
require_once __DIR__ . '/../helpers/UUID.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/AuditController.php';

class GiftStockController {

    public static function index(): void {
        AuthMiddleware::authenticate();
        $pdo = getDbConnection();
        $type = $_GET['type'] ?? null;
        if ($type) {
            $stmt = $pdo->prepare("SELECT * FROM gift_stock WHERE is_active = 1 AND unit = ? ORDER BY name");
            $stmt->execute([$type]);
        } else {
            $stmt = $pdo->query("SELECT * FROM gift_stock WHERE is_active = 1 ORDER BY name");
        }
        $items = $stmt->fetchAll();
        foreach ($items as &$item) {
            $sumStmt = $pdo->prepare("SELECT movement_type, SUM(quantity) AS total FROM gift_stock_movements WHERE gift_id = ? GROUP BY movement_type");
            $sumStmt->execute([$item['id']]);
            $totals = ['received' => 0, 'issued' => 0];
            foreach ($sumStmt->fetchAll() as $row) {
                $totals[$row['movement_type']] = (int)$row['total'];
            }
            $item['total_received'] = $totals['received'];
            $item['total_issued'] = $totals['issued'];
            $item['quantity'] = $totals['received'] - $totals['issued'];
            $item['stock_value'] = round($item['quantity'] * $item['unit_price'], 2);
        }
        Response::success($items);
    }

    public static function show(string $id): void {
        AuthMiddleware::authenticate();
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("SELECT * FROM gift_stock WHERE id = ?");
        $stmt->execute([$id]);
        $item = $stmt->fetch();
        if (!$item) Response::error('Gift not found', 404);
        Response::success($item);
    }

    public static function store(): void {
        AuthMiddleware::requireAnyRole(['admin', 'treasurer']);
        $input = json_decode(file_get_contents('php://input'), true);

        $v = new Validator();
        $v->required($input['name'] ?? '', 'Name')
           ->required($input['unit_price'] ?? '', 'Unit Price')
           ->numeric($input['unit_price'] ?? '', 'Unit Price')
           ->min($input['unit_price'] ?? 0, 0, 'Unit Price');
        if (!$v->passes()) Response::error('Validation failed', 422, $v->getErrors());

        $id = UUID::v4();
        $pdo = getDbConnection();
        $pdo->beginTransaction();
        try {
            $id = UUID::v4();
            $quantity = (int)($input['quantity'] ?? 0);

            $pdo->prepare("INSERT INTO gift_stock (id, name, unit, unit_price, quantity) VALUES (?, ?, ?, ?, ?)")
                ->execute([$id, $input['name'], $input['unit'] ?? 'piece', $input['unit_price'], $quantity]);

            if ($quantity > 0) {
                $movementId = UUID::v4();
                $pdo->prepare("INSERT INTO gift_stock_movements (id, gift_id, movement_type, quantity, unit_price, notes, created_by)
                               VALUES (?, ?, 'received', ?, ?, ?, ?)")
                    ->execute([$movementId, $id, $quantity, $input['unit_price'], 'Initial stock', AuthMiddleware::getUserId()]);
            }

            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            Response::error('Failed to create gift', 500);
        }

        AuditController::log('gift_stock', $id, 'create', null, $input, AuthMiddleware::getUserId());

        $stmt = $pdo->prepare("SELECT * FROM gift_stock WHERE id = ?");
        $stmt->execute([$id]);
        Response::success($stmt->fetch(), 'Gift created', 201);
    }

    public static function update(string $id): void {
        AuthMiddleware::requireAnyRole(['admin', 'treasurer']);
        $input = json_decode(file_get_contents('php://input'), true);
        $pdo = getDbConnection();

        $stmt = $pdo->prepare("SELECT * FROM gift_stock WHERE id = ?");
        $stmt->execute([$id]);
        $old = $stmt->fetch();
        if (!$old) Response::error('Gift not found', 404);

        $fields = []; $params = [];
        foreach (['name', 'unit'] as $f) {
            if (isset($input[$f])) { $fields[] = "$f = ?"; $params[] = $input[$f]; }
        }
        if (isset($input['unit_price'])) {
            $v = new Validator();
            $v->numeric($input['unit_price'], 'Unit Price')->min($input['unit_price'], 0, 'Unit Price');
            if (!$v->passes()) Response::error('Validation failed', 422, $v->getErrors());
            $fields[] = 'unit_price = ?'; $params[] = $input['unit_price'];
        }
        if (isset($input['is_active'])) { $fields[] = 'is_active = ?'; $params[] = $input['is_active'] ? 1 : 0; }

        if (empty($fields)) Response::error('No fields to update', 400);
        $params[] = $id;
        $pdo->prepare("UPDATE gift_stock SET " . implode(', ', $fields) . " WHERE id = ?")->execute($params);

        AuditController::log('gift_stock', $id, 'update', $old, array_merge($old, $input), AuthMiddleware::getUserId());

        $stmt = $pdo->prepare("SELECT * FROM gift_stock WHERE id = ?");
        $stmt->execute([$id]);
        Response::success($stmt->fetch(), 'Gift updated');
    }

    public static function destroy(string $id): void {
        AuthMiddleware::requireAnyRole(['admin', 'treasurer']);
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("SELECT * FROM gift_stock WHERE id = ?");
        $stmt->execute([$id]);
        $old = $stmt->fetch();
        if (!$old) Response::error('Gift not found', 404);

        $pdo->prepare("UPDATE gift_stock SET is_active = 0 WHERE id = ?")->execute([$id]);
        AuditController::log('gift_stock', $id, 'delete', $old, ['is_active' => 0], AuthMiddleware::getUserId());
        Response::success(null, 'Gift deactivated');
    }

    public static function receive(string $id): void {
        AuthMiddleware::requireAnyRole(['admin', 'treasurer']);
        $input = json_decode(file_get_contents('php://input'), true);

        $v = new Validator();
        $v->required($input['quantity'] ?? '', 'Quantity')
           ->numeric($input['quantity'] ?? '', 'Quantity')
           ->min($input['quantity'] ?? 0, 1, 'Quantity')
           ->numeric($input['unit_price'] ?? '', 'Unit Price')
           ->min($input['unit_price'] ?? 0, 0, 'Unit Price');
        if (!$v->passes()) Response::error('Validation failed', 422, $v->getErrors());

        $pdo = getDbConnection();
        $stmt = $pdo->prepare("SELECT * FROM gift_stock WHERE id = ?");
        $stmt->execute([$id]);
        $gift = $stmt->fetch();
        if (!$gift) Response::error('Gift not found', 404);

        $quantity = (int)$input['quantity'];
        $unitPrice = (float)$input['unit_price'];

        $pdo->beginTransaction();
        try {
            $pdo->prepare("UPDATE gift_stock SET quantity = quantity + ?, unit_price = ?, updated_at = NOW() WHERE id = ?")
                ->execute([$quantity, $unitPrice, $id]);

            $movementId = UUID::v4();
            $pdo->prepare("INSERT INTO gift_stock_movements (id, gift_id, movement_type, quantity, unit_price, notes, created_by)
                           VALUES (?, ?, 'received', ?, ?, ?, ?)")
                ->execute([$movementId, $id, $quantity, $unitPrice, $input['notes'] ?? null, AuthMiddleware::getUserId()]);

            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            Response::error('Failed to record receipt: ' . $e->getMessage(), 500);
        }

        AuditController::log('gift_stock', $id, 'receive', $gift, ['quantity' => $quantity, 'unit_price' => $unitPrice], AuthMiddleware::getUserId());

        $stmt = $pdo->prepare("SELECT * FROM gift_stock WHERE id = ?");
        $stmt->execute([$id]);
        Response::success($stmt->fetch(), 'Stock received');
    }

    public static function issue(string $id): void {
        AuthMiddleware::requireAnyRole(['admin', 'treasurer', 'organizer']);
        $input = json_decode(file_get_contents('php://input'), true);

        $v = new Validator();
        $v->required($input['quantity'] ?? '', 'Quantity')
           ->numeric($input['quantity'] ?? '', 'Quantity')
           ->min($input['quantity'] ?? 0, 1, 'Quantity');
        if (!$v->passes()) Response::error('Validation failed', 422, $v->getErrors());

        $pdo = getDbConnection();
        $stmt = $pdo->prepare("SELECT * FROM gift_stock WHERE id = ?");
        $stmt->execute([$id]);
        $gift = $stmt->fetch();
        if (!$gift) Response::error('Gift not found', 404);

        $quantity = (int)$input['quantity'];
        if ($quantity > (int)$gift['quantity']) {
            Response::error('Insufficient stock. Only ' . (int)$gift['quantity'] . ' available', 422);
        }

        $eventId = $input['event_id'] ?? null;
        if ($eventId) {
            $ev = $pdo->prepare("SELECT id FROM events WHERE id = ?");
            $ev->execute([$eventId]);
            if (!$ev->fetch()) Response::error('Event not found', 404);
        }

        $memberId = $input['member_id'] ?? null;
        if ($memberId) {
            $mem = $pdo->prepare("SELECT id FROM members WHERE id = ?");
            $mem->execute([$memberId]);
            if (!$mem->fetch()) Response::error('Member not found', 404);
        }

        $pdo->beginTransaction();
        try {
            $pdo->prepare("UPDATE gift_stock SET quantity = quantity - ?, updated_at = NOW() WHERE id = ?")
                ->execute([$quantity, $id]);

            $movementId = UUID::v4();
            $pdo->prepare("INSERT INTO gift_stock_movements (id, gift_id, movement_type, quantity, unit_price, event_id, member_id, notes, created_by)
                           VALUES (?, ?, 'issued', ?, ?, ?, ?, ?, ?)")
                ->execute([$movementId, $id, $quantity, $gift['unit_price'], $eventId, $memberId, $input['notes'] ?? null, AuthMiddleware::getUserId()]);

            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            Response::error('Failed to record issue: ' . $e->getMessage(), 500);
        }

        AuditController::log('gift_stock', $id, 'issue', $gift, ['quantity' => $quantity], AuthMiddleware::getUserId());

        $stmt = $pdo->prepare("SELECT * FROM gift_stock WHERE id = ?");
        $stmt->execute([$id]);
        Response::success($stmt->fetch(), 'Stock issued');
    }

    public static function movements(string $id): void {
        AuthMiddleware::authenticate();
        $pdo = getDbConnection();

        $stmt = $pdo->prepare("SELECT * FROM gift_stock WHERE id = ?");
        $stmt->execute([$id]);
        if (!$stmt->fetch()) Response::error('Gift not found', 404);

        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = min(200, max(1, (int)($_GET['per_page'] ?? 50)));
        $offset = ($page - 1) * $perPage;

        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM gift_stock_movements WHERE gift_id = ?");
        $countStmt->execute([$id]);
        $total = $countStmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT m.*, u.name AS created_by_name, e.name AS event_name, mb.name AS member_name
                               FROM gift_stock_movements m
                               LEFT JOIN users u ON m.created_by = u.id
                               LEFT JOIN events e ON m.event_id = e.id
                               LEFT JOIN members mb ON m.member_id = mb.id
                               WHERE m.gift_id = ?
                               ORDER BY m.created_at DESC
                               LIMIT ? OFFSET ?");
        $stmt->execute([$id, $perPage, $offset]);
        $rows = $stmt->fetchAll();
        foreach ($rows as &$r) {
            $r['total_value'] = round($r['quantity'] * $r['unit_price'], 2);
        }
        Response::paginated($rows, (int)$total, $page, $perPage);
    }

    public static function updateMovement(string $movementId): void {
        AuthMiddleware::requireAnyRole(['admin', 'treasurer']);
        $input = json_decode(file_get_contents('php://input'), true);
        $pdo = getDbConnection();

        $stmt = $pdo->prepare("SELECT * FROM gift_stock_movements WHERE id = ?");
        $stmt->execute([$movementId]);
        $movement = $stmt->fetch();
        if (!$movement) Response::error('Movement not found', 404);

        $v = new Validator();
        $v->required($input['quantity'] ?? '', 'Quantity')
           ->numeric($input['quantity'] ?? '', 'Quantity')
           ->min($input['quantity'] ?? 0, 1, 'Quantity');
        if (!$v->passes()) Response::error('Validation failed', 422, $v->getErrors());

        $newQty = (int)$input['quantity'];
        $oldQty = (int)$movement['quantity'];
        $delta = $newQty - $oldQty;

        if ($movement['movement_type'] === 'issued') {
            $gift = $pdo->prepare("SELECT * FROM gift_stock WHERE id = ?");
            $gift->execute([$movement['gift_id']]);
            $g = $gift->fetch();
            if (!$g) Response::error('Gift not found', 404);

            // increasing issued qty: need enough stock; decreasing: return stock
            if ($delta > 0 && (int)$g['quantity'] < $delta) {
                Response::error('Insufficient stock. Only ' . (int)$g['quantity'] . ' available', 422);
            }
            $pdo->prepare("UPDATE gift_stock SET quantity = quantity - ?, updated_at = NOW() WHERE id = ?")
                ->execute([$delta, $movement['gift_id']]);
        } else {
            // received: adjust stock back (increase stock when reducing received qty)
            $pdo->prepare("UPDATE gift_stock SET quantity = quantity + ?, updated_at = NOW() WHERE id = ?")
                ->execute([-$delta, $movement['gift_id']]);
        }

        $fields = ['quantity = ?', 'notes = ?'];
        $params = [$newQty, $input['notes'] ?? $movement['notes']];

        if (isset($input['unit_price'])) {
            $fields[] = 'unit_price = ?';
            $params[] = (float)$input['unit_price'];
        }

        $params[] = $movementId;
        $pdo->prepare("UPDATE gift_stock_movements SET " . implode(', ', $fields) . " WHERE id = ?")->execute($params);

        AuditController::log('gift_stock_movements', $movementId, 'update', $movement, array_merge($movement, $input), AuthMiddleware::getUserId());
        Response::success(null, 'Movement updated');
    }

    public static function destroyMovement(string $movementId): void {
        AuthMiddleware::requireAnyRole(['admin', 'treasurer']);
        $pdo = getDbConnection();

        $stmt = $pdo->prepare("SELECT * FROM gift_stock_movements WHERE id = ?");
        $stmt->execute([$movementId]);
        $movement = $stmt->fetch();
        if (!$movement) Response::error('Movement not found', 404);

        if ($movement['movement_type'] === 'issued') {
            $pdo->prepare("UPDATE gift_stock SET quantity = quantity + ?, updated_at = NOW() WHERE id = ?")
                ->execute([$movement['quantity'], $movement['gift_id']]);
        } else {
            $gift = $pdo->prepare("SELECT * FROM gift_stock WHERE id = ?");
            $gift->execute([$movement['gift_id']]);
            $g = $gift->fetch();
            $remaining = max(0, (int)($g['quantity'] ?? 0) - (int)$movement['quantity']);
            $pdo->prepare("UPDATE gift_stock SET quantity = ?, updated_at = NOW() WHERE id = ?")
                ->execute([$remaining, $movement['gift_id']]);
        }

        $pdo->prepare("DELETE FROM gift_stock_movements WHERE id = ?")->execute([$movementId]);

        AuditController::log('gift_stock_movements', $movementId, 'delete', $movement, null, AuthMiddleware::getUserId());
        Response::success(null, 'Movement deleted');
    }
}