<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/UUID.php';
require_once __DIR__ . '/../helpers/Response.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';

class AuditController {

    public static function log(string $entityType, string $entityId, string $action, $oldValues, $newValues, string $changedBy): void {
        try {
            $pdo = getDbConnection();
            $id = UUID::v4();
            $stmt = $pdo->prepare("INSERT INTO audit_log (id, entity_type, entity_id, action, old_values, new_values, changed_by)
                                   VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $id, $entityType, $entityId, $action,
                $oldValues ? json_encode($oldValues) : null,
                $newValues ? json_encode($newValues) : null,
                $changedBy,
            ]);
        } catch (Throwable $e) {
            // Silently fail — audit should never break the main operation
            error_log("Audit log error: " . $e->getMessage());
        }
    }

    public static function index(): void {
        AuthMiddleware::requireAnyRole(['admin', 'treasurer', 'board']);
        $pdo = getDbConnection();

        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = min(100, max(1, (int)($_GET['per_page'] ?? 50)));
        $offset = ($page - 1) * $perPage;

        $where = "WHERE 1=1";
        $params = [];

        if (!empty($_GET['entity_type'])) { $where .= " AND a.entity_type = ?"; $params[] = $_GET['entity_type']; }
        if (!empty($_GET['entity_id'])) { $where .= " AND a.entity_id = ?"; $params[] = $_GET['entity_id']; }
        if (!empty($_GET['action'])) { $where .= " AND a.action = ?"; $params[] = $_GET['action']; }

        $countSql = "SELECT COUNT(*) FROM audit_log a $where";
        $total = $pdo->prepare($countSql);
        $total->execute($params);
        $totalCount = $total->fetchColumn();

        $sql = "SELECT a.*, m.name AS changed_by_name
                FROM audit_log a
                LEFT JOIN users m ON a.changed_by = m.id
                $where
                ORDER BY a.timestamp DESC
                LIMIT ? OFFSET ?";
        $params[] = $perPage;
        $params[] = $offset;
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        Response::paginated($stmt->fetchAll(), (int)$totalCount, $page, $perPage);
    }
}
