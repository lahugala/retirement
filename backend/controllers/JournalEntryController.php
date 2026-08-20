<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/Response.php';
require_once __DIR__ . '/../helpers/UUID.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';

class JournalEntryController {

    public static function index(): void {
        AuthMiddleware::authenticate();
        $pdo = getDbConnection();

        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = min(100, max(1, (int)($_GET['per_page'] ?? 20)));
        $offset = ($page - 1) * $perPage;

        $where = "WHERE 1=1";
        $params = [];

        if (!empty($_GET['date_from'])) { $where .= " AND je.entry_date >= ?"; $params[] = $_GET['date_from']; }
        if (!empty($_GET['date_to'])) { $where .= " AND je.entry_date <= ?"; $params[] = $_GET['date_to']; }
        if (!empty($_GET['reference_type'])) { $where .= " AND je.reference_type = ?"; $params[] = $_GET['reference_type']; }
        if (!empty($_GET['reference_id'])) { $where .= " AND je.reference_id = ?"; $params[] = $_GET['reference_id']; }

        $countSql = "SELECT COUNT(*) FROM journal_entries je $where";
        $total = $pdo->prepare($countSql);
        $total->execute($params);
        $totalCount = $total->fetchColumn();

        $sql = "SELECT je.*, u.name AS created_by_name
                FROM journal_entries je
                JOIN users u ON je.created_by = u.id
                $where
                ORDER BY je.entry_date DESC, je.created_at DESC
                LIMIT ? OFFSET ?";
        $params[] = $perPage;
        $params[] = $offset;
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $entries = $stmt->fetchAll();

        // Fetch lines for each entry
        if (!empty($entries)) {
            $ids = array_column($entries, 'id');
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $lineStmt = $pdo->prepare("SELECT jl.*, a.code AS account_code, a.name AS account_name, a.type AS account_type
                                       FROM journal_lines jl
                                       JOIN accounts a ON jl.account_id = a.id
                                       WHERE jl.journal_entry_id IN ($placeholders)
                                       ORDER BY jl.id");
            $lineStmt->execute($ids);
            $lines = $lineStmt->fetchAll();

            $linesByEntry = [];
            foreach ($lines as $line) {
                $linesByEntry[$line['journal_entry_id']][] = $line;
            }
            foreach ($entries as &$entry) {
                $entry['lines'] = $linesByEntry[$entry['id']] ?? [];
                $entry['total_debit'] = array_sum(array_column($entry['lines'], 'debit'));
                $entry['total_credit'] = array_sum(array_column($entry['lines'], 'credit'));
            }
        }

        Response::paginated($entries, (int)$totalCount, $page, $perPage);
    }

    public static function show(string $id): void {
        AuthMiddleware::authenticate();
        $pdo = getDbConnection();

        $stmt = $pdo->prepare("SELECT je.*, u.name AS created_by_name
                               FROM journal_entries je
                               JOIN users u ON je.created_by = u.id
                               WHERE je.id = ?");
        $stmt->execute([$id]);
        $entry = $stmt->fetch();
        if (!$entry) Response::error('Journal entry not found', 404);

        $lineStmt = $pdo->prepare("SELECT jl.*, a.code AS account_code, a.name AS account_name, a.type AS account_type
                                   FROM journal_lines jl
                                   JOIN accounts a ON jl.account_id = a.id
                                   WHERE jl.journal_entry_id = ?
                                   ORDER BY jl.id");
        $lineStmt->execute([$id]);
        $entry['lines'] = $lineStmt->fetchAll();
        $entry['total_debit'] = array_sum(array_column($entry['lines'], 'debit'));
        $entry['total_credit'] = array_sum(array_column($entry['lines'], 'credit'));

        Response::success($entry);
    }
}
