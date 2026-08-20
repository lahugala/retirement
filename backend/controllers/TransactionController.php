<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/Response.php';
require_once __DIR__ . '/../helpers/Validator.php';
require_once __DIR__ . '/../helpers/UUID.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/AuditController.php';

class TransactionController {

    public static function index(): void {
        AuthMiddleware::authenticate();
        $pdo = getDbConnection();
        $role = AuthMiddleware::getUserRole();
        $userId = AuthMiddleware::getUserId();

        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = min(100, max(1, (int)($_GET['per_page'] ?? 20)));
        $offset = ($page - 1) * $perPage;

        $where = "WHERE t.is_deleted = 0";
        $params = [];

        // Organizers see only their own event transactions
        if ($role === 'organizer') {
            $where .= " AND (t.created_by = ? OR t.event_id IN (SELECT id FROM events WHERE organizer_id = ?))";
            $params[] = $userId;
            $params[] = $userId;
        }

        // Filters
        if (!empty($_GET['type'])) { $where .= " AND t.type = ?"; $params[] = $_GET['type']; }
        if (!empty($_GET['status'])) { $where .= " AND t.status = ?"; $params[] = $_GET['status']; }
        if (!empty($_GET['event_id'])) { $where .= " AND t.event_id = ?"; $params[] = $_GET['event_id']; }
        if (!empty($_GET['category_id'])) { $where .= " AND t.category_id = ?"; $params[] = $_GET['category_id']; }
        if (!empty($_GET['date_from'])) { $where .= " AND t.transaction_date >= ?"; $params[] = $_GET['date_from']; }
        if (!empty($_GET['date_to'])) { $where .= " AND t.transaction_date <= ?"; $params[] = $_GET['date_to']; }
        if (!empty($_GET['designated_retiree'])) { $where .= " AND t.designated_retiree LIKE ?"; $params[] = '%' . $_GET['designated_retiree'] . '%'; }

        $countSql = "SELECT COUNT(*) FROM transactions t $where";
        $total = $pdo->prepare($countSql);
        $total->execute($params);
        $totalCount = $total->fetchColumn();

        $sql = "SELECT t.*, c.name AS category_name, c.type AS category_type,
                       c.default_account_id, a.code AS account_code, a.name AS account_name,
                       e.name AS event_name, e.status AS event_status,
                       m.name AS created_by_name, ap.name AS approved_by_name
                FROM transactions t
                JOIN categories c ON t.category_id = c.id
                LEFT JOIN accounts a ON c.default_account_id = a.id
                LEFT JOIN events e ON t.event_id = e.id
                LEFT JOIN users m ON t.created_by = m.id
                LEFT JOIN users ap ON t.approved_by = ap.id
                $where
                ORDER BY t.transaction_date DESC, t.created_at DESC
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
        $stmt = $pdo->prepare("SELECT t.*, c.name AS category_name, c.type AS category_type,
                                      c.default_account_id, a.code AS account_code, a.name AS account_name,
                                      e.name AS event_name, e.status AS event_status,
                                      m.name AS created_by_name, ap.name AS approved_by_name
                               FROM transactions t
                               JOIN categories c ON t.category_id = c.id
                               LEFT JOIN accounts a ON c.default_account_id = a.id
                               LEFT JOIN events e ON t.event_id = e.id
                               LEFT JOIN users m ON t.created_by = m.id
                               LEFT JOIN users ap ON t.approved_by = ap.id
                               WHERE t.id = ?");
        $stmt->execute([$id]);
        $txn = $stmt->fetch();
        if (!$txn) Response::error('Transaction not found', 404);
        Response::success($txn);
    }

    public static function store(): void {
        AuthMiddleware::authenticate();
        $input = json_decode(file_get_contents('php://input'), true);
        $userId = AuthMiddleware::getUserId();
        $role = AuthMiddleware::getUserRole();

        $v = new Validator();
        $v->required($input['type'] ?? '', 'Type')->inArray($input['type'] ?? '', ['income', 'expense'], 'Type')
           ->required($input['category_id'] ?? '', 'Category')
           ->required($input['amount'] ?? '', 'Amount')->numeric($input['amount'] ?? '', 'Amount')->min($input['amount'] ?? 0, 0.01, 'Amount')
           ->required($input['transaction_date'] ?? '', 'Transaction date')->date($input['transaction_date'] ?? '', 'Transaction date');
        if (!$v->passes()) Response::error('Validation failed', 422, $v->getErrors());

        $pdo = getDbConnection();

        // Validate that category type matches transaction type
        $catStmt = $pdo->prepare("SELECT type FROM categories WHERE id = ?");
        $catStmt->execute([$input['category_id']]);
        $catType = $catStmt->fetchColumn();
        if (!$catType) Response::error('Category not found', 404);
        if ($catType !== $input['type']) {
            Response::error("Category type '{$catType}' does not match transaction type '{$input['type']}'", 422);
        }

        // DUPLICATE DETECTION: Check recent transactions with same amount, payee, within 7 days
        if (!empty($input['payee'])) {
            $dupStmt = $pdo->prepare("SELECT id, transaction_date, payee, amount FROM transactions
                                      WHERE is_deleted = 0
                                      AND ABS(amount - ?) <= 1
                                      AND payee = ?
                                      AND transaction_date >= DATE_SUB(?, INTERVAL 7 DAY)
                                      LIMIT 1");
            $dupStmt->execute([$input['amount'], $input['payee'], $input['transaction_date']]);
            $duplicate = $dupStmt->fetch();
            if ($duplicate && empty($input['confirm_duplicate'])) {
                Response::json([
                    'success' => false,
                    'message' => 'Potential duplicate detected',
                    'data'    => ['duplicate' => $duplicate],
                ], 409);
            }
        }

        // Organizer restricted to expense under threshold
        $status = 'approved';
        if ($input['type'] === 'expense') {
            $amount = (float)$input['amount'];
            if ($amount > APPROVAL_THRESHOLD && !in_array($role, ['admin', 'treasurer'])) {
                $status = 'pending_approval';
            }
        }

        // If role is organizer, only member/board can set to pending_approval; auto-approve small expenses
        if ($role === 'organizer' && $input['type'] === 'expense' && (float)$input['amount'] <= APPROVAL_THRESHOLD) {
            // Auto-approved for small amounts, set approved_by to self
        } elseif ($role === 'organizer' && $input['type'] === 'expense') {
            $status = 'pending_approval';
        }

        $id = UUID::v4();
        $stmt = $pdo->prepare("INSERT INTO transactions (id, type, event_id, category_id, amount, transaction_date, payee, description, payment_method, receipt_path, designated_retiree, created_by, status)
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $id, $input['type'], $input['event_id'] ?? null, $input['category_id'],
            $input['amount'], $input['transaction_date'], $input['payee'] ?? null,
            $input['description'] ?? null, $input['payment_method'] ?? 'cash',
            $input['receipt_path'] ?? null, $input['designated_retiree'] ?? null,
            $userId, $status,
        ]);

        // Update budget actual_spent if linked to an event
        if (!empty($input['event_id']) && $input['type'] === 'expense') {
            $pdo->prepare("UPDATE budgets SET actual_spent = (
                SELECT COALESCE(SUM(amount), 0) FROM transactions
                WHERE event_id = ? AND category_id = ? AND type = 'expense' AND is_deleted = 0 AND status IN ('approved','pending_approval')
            ) WHERE event_id = ? AND category_id = ?")
            ->execute([$input['event_id'], $input['category_id'], $input['event_id'], $input['category_id']]);
        }

        // Post journal entry if auto-approved
        if ($status === 'approved') {
            self::postJournalForTransaction($id, $pdo, $userId);
        }

        AuditController::log('transactions', $id, 'create', null, $input, $userId);

        $stmt = $pdo->prepare("SELECT t.*, c.name AS category_name, c.type AS category_type, c.default_account_id, a.code AS account_code, a.name AS account_name FROM transactions t JOIN categories c ON t.category_id = c.id LEFT JOIN accounts a ON c.default_account_id = a.id WHERE t.id = ?");
        $stmt->execute([$id]);
        Response::success($stmt->fetch(), 'Transaction created', 201);
    }

    public static function update(string $id): void {
        AuthMiddleware::authenticate();
        $input = json_decode(file_get_contents('php://input'), true);
        $userId = AuthMiddleware::getUserId();
        $role = AuthMiddleware::getUserRole();
        $pdo = getDbConnection();

        $stmt = $pdo->prepare("SELECT * FROM transactions WHERE id = ?");
        $stmt->execute([$id]);
        $old = $stmt->fetch();
        if (!$old) Response::error('Transaction not found', 404);

        // Only creator, treasurer, admin can edit
        if (!in_array($role, ['admin', 'treasurer']) && $old['created_by'] !== $userId) {
            Response::error('You can only edit your own transactions', 403);
        }

        // Cannot edit if approved
        if ($old['status'] === 'approved' && !in_array($role, ['admin'])) {
            Response::error('Cannot edit an approved transaction', 403);
        }

        // Cannot edit if linked to a completed or cancelled event
        if ($old['event_id']) {
            $evtStmt = $pdo->prepare("SELECT status FROM events WHERE id = ?");
            $evtStmt->execute([$old['event_id']]);
            $eventStatus = $evtStmt->fetchColumn();
            if (in_array($eventStatus, ['completed', 'cancelled'])) {
                Response::error("Cannot edit a transaction linked to a {$eventStatus} event", 403);
            }
        }

        $fields = [];
        $params = [];
        foreach (['type', 'event_id', 'category_id', 'amount', 'transaction_date', 'payee', 'description', 'payment_method', 'receipt_path', 'designated_retiree'] as $f) {
            if (array_key_exists($f, $input)) { $fields[] = "$f = ?"; $params[] = $input[$f]; }
        }

        if (empty($fields)) Response::error('No fields to update', 400);
        $params[] = $id;
        $pdo->prepare("UPDATE transactions SET " . implode(', ', $fields) . " WHERE id = ?")->execute($params);

        // Re-post journal entry if approved
        $newStatus = $input['status'] ?? $old['status'];
        if ($newStatus === 'approved' || $old['status'] === 'approved') {
            self::postJournalForTransaction($id, $pdo, $userId);
        }

        AuditController::log('transactions', $id, 'update', $old, array_merge($old, $input), $userId);

        $stmt = $pdo->prepare("SELECT t.*, c.name AS category_name FROM transactions t JOIN categories c ON t.category_id = c.id WHERE t.id = ?");
        $stmt->execute([$id]);
        Response::success($stmt->fetch(), 'Transaction updated');
    }

    public static function approve(string $id): void {
        AuthMiddleware::requireAnyRole(['admin', 'treasurer', 'board']);
        $userId = AuthMiddleware::getUserId();
        $pdo = getDbConnection();

        $stmt = $pdo->prepare("SELECT * FROM transactions WHERE id = ?");
        $stmt->execute([$id]);
        $old = $stmt->fetch();
        if (!$old) Response::error('Transaction not found', 404);
        if ($old['status'] !== 'pending_approval') Response::error('Transaction is not pending approval', 400);
        if ($old['created_by'] === $userId) Response::error('You cannot approve your own transaction', 403);

        $pdo->prepare("UPDATE transactions SET status = 'approved', approved_by = ? WHERE id = ?")->execute([$userId, $id]);

        // Update budget actual_spent
        if ($old['event_id'] && $old['type'] === 'expense') {
            $pdo->prepare("UPDATE budgets SET actual_spent = (
                SELECT COALESCE(SUM(amount), 0) FROM transactions
                WHERE event_id = ? AND category_id = ? AND type = 'expense' AND is_deleted = 0 AND status IN ('approved','pending_approval')
            ) WHERE event_id = ? AND category_id = ?")
            ->execute([$old['event_id'], $old['category_id'], $old['event_id'], $old['category_id']]);
        }

        // Post journal entry
        self::postJournalForTransaction($id, $pdo, $userId);

        AuditController::log('transactions', $id, 'approve', $old, ['status' => 'approved', 'approved_by' => $userId], $userId);
        Response::success(['id' => $id, 'status' => 'approved'], 'Transaction approved');
    }

    public static function reject(string $id): void {
        AuthMiddleware::requireAnyRole(['admin', 'treasurer', 'board']);
        $input = json_decode(file_get_contents('php://input'), true);
        $userId = AuthMiddleware::getUserId();
        $pdo = getDbConnection();

        $stmt = $pdo->prepare("SELECT * FROM transactions WHERE id = ?");
        $stmt->execute([$id]);
        $old = $stmt->fetch();
        if (!$old) Response::error('Transaction not found', 404);
        if ($old['status'] !== 'pending_approval') Response::error('Transaction is not pending approval', 400);
        if ($old['created_by'] === $userId) Response::error('You cannot reject your own transaction', 403);

        $reason = $input['reject_reason'] ?? 'No reason provided';
        $pdo->prepare("UPDATE transactions SET status = 'rejected', description = CONCAT(description, ' [REJECTED: ', ?, ']') WHERE id = ?")->execute([$reason, $id]);
        AuditController::log('transactions', $id, 'reject', $old, ['status' => 'rejected', 'reason' => $reason], $userId);
        Response::success(['id' => $id, 'status' => 'rejected'], 'Transaction rejected');
    }

    public static function destroy(string $id): void {
        AuthMiddleware::authenticate();
        $input = json_decode(file_get_contents('php://input'), true);
        $userId = AuthMiddleware::getUserId();
        $role = AuthMiddleware::getUserRole();
        $pdo = getDbConnection();

        $stmt = $pdo->prepare("SELECT * FROM transactions WHERE id = ?");
        $stmt->execute([$id]);
        $old = $stmt->fetch();
        if (!$old) Response::error('Transaction not found', 404);

        // Only admin can soft-delete; others get error
        if (!in_array($role, ['admin'])) {
            Response::error('Only administrators can delete transactions', 403);
        }

        $reason = $input['delete_reason'] ?? 'No reason provided';
        $pdo->prepare("UPDATE transactions SET is_deleted = 1, delete_reason = ? WHERE id = ?")->execute([$reason, $id]);

        // Remove associated journal entry if any
        $pdo->prepare("DELETE FROM journal_entries WHERE reference_type = 'transaction' AND reference_id = ?")->execute([$id]);

        AuditController::log('transactions', $id, 'soft_delete', $old, ['is_deleted' => 1, 'delete_reason' => $reason], $userId);
        Response::success(null, 'Transaction soft-deleted');
    }

    // -----------------------------------------------------------
    // Journal entry posting (double-entry accounting)
    // -----------------------------------------------------------
    private static function postJournalForTransaction(string $txnId, PDO $pdo, string $userId): void {
        // Remove existing journal entry if re-posting
        $pdo->prepare("DELETE FROM journal_entries WHERE reference_type = 'transaction' AND reference_id = ?")->execute([$txnId]);

        $stmt = $pdo->prepare("SELECT t.*, c.default_account_id FROM transactions t JOIN categories c ON t.category_id = c.id WHERE t.id = ?");
        $stmt->execute([$txnId]);
        $txn = $stmt->fetch();
        if (!$txn || !$txn['default_account_id']) return;

        $jeId = UUID::v4();
        $refNum = 'TXN-' . strtoupper(substr($txnId, 0, 8));
        $desc = $txn['description'] ?: ($txn['type'] === 'income' ? 'Income' : 'Expense');

        $pdo->prepare("INSERT INTO journal_entries (id, entry_date, ref_num, description, reference_type, reference_id, created_by)
                       VALUES (?, ?, ?, ?, 'transaction', ?, ?)")
            ->execute([$jeId, $txn['transaction_date'], $refNum, $desc, $txnId, $userId]);

        if ($txn['type'] === 'income') {
            // Debit Cash, Credit Income account
            $line1Id = UUID::v4();
            $line2Id = UUID::v4();
            $pdo->prepare("INSERT INTO journal_lines (id, journal_entry_id, account_id, debit, credit, description) VALUES (?, ?, ?, ?, ?, ?)")
                ->execute([$line1Id, $jeId, 'a1000000-0000-0000-0000-000000000001', $txn['amount'], 0, $desc]);
            $pdo->prepare("INSERT INTO journal_lines (id, journal_entry_id, account_id, debit, credit, description) VALUES (?, ?, ?, ?, ?, ?)")
                ->execute([$line2Id, $jeId, $txn['default_account_id'], 0, $txn['amount'], $desc]);
        } else {
            // Debit Expense account, Credit Cash
            $line1Id = UUID::v4();
            $line2Id = UUID::v4();
            $pdo->prepare("INSERT INTO journal_lines (id, journal_entry_id, account_id, debit, credit, description) VALUES (?, ?, ?, ?, ?, ?)")
                ->execute([$line1Id, $jeId, $txn['default_account_id'], $txn['amount'], 0, $desc]);
            $pdo->prepare("INSERT INTO journal_lines (id, journal_entry_id, account_id, debit, credit, description) VALUES (?, ?, ?, ?, ?, ?)")
                ->execute([$line2Id, $jeId, 'a1000000-0000-0000-0000-000000000001', 0, $txn['amount'], $desc]);
        }

        AuditController::log('journal_entries', $jeId, 'create', null, ['ref_num' => $refNum, 'txn_id' => $txnId], $userId);
    }
}
