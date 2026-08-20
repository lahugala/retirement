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
        $perPage = min(1000, max(1, (int)($_GET['per_page'] ?? 20)));
        $offset = ($page - 1) * $perPage;
        $search = $_GET['search'] ?? '';
        $status = $_GET['status'] ?? '';

        $where = '';
        $params = [];
        if ($search !== '') {
            $where = "WHERE name LIKE ? OR nic LIKE ? OR service_no LIKE ? OR computer_no LIKE ?";
            $like = "%{$search}%";
            $params = [$like, $like, $like, $like];
        }
        if ($status !== '') {
            $valid = ['active', 'retired', 'deceased', 'resigned', 'inactive', 'dismissed'];
            if (!in_array($status, $valid)) Response::error('Invalid status filter', 422);
            if ($where === '') {
                $where = "WHERE status = ?";
            } else {
                $where .= " AND status = ?";
            }
            $params[] = $status;
        }

        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM members $where");
        $countStmt->execute($params);
        $total = $countStmt->fetchColumn();

        $params[] = $perPage;
        $params[] = $offset;
        $stmt = $pdo->prepare("SELECT * FROM members $where ORDER BY (retirement_date IS NULL) ASC, retirement_date ASC, name ASC LIMIT ? OFFSET ?");
        $stmt->execute($params);
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

        $stmt = $pdo->prepare("INSERT INTO members (id, name, nic, service_no, designation, computer_no, retirement_date, status)
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $id,
            $input['name'],
            $input['nic'] ?? null,
            $input['service_no'] ?? null,
            $input['designation'] ?? null,
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
        foreach (['name', 'nic', 'service_no', 'designation', 'computer_no', 'status'] as $f) {
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

    // -----------------------------------------------------------
    // Sync retirement date from external employee API
    // -----------------------------------------------------------
    private static function fetchEmployee(string $computerNo): ?array {
        $url = EMPLOYEE_API_BASE . '/GetEmpByComputeNo?computerNo=' . urlencode($computerNo);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $resp = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($resp === false || $httpCode !== 200) return null;
        $data = json_decode($resp, true);
        $records = $data['Data'] ?? [];
        return empty($records) ? null : $records[0];
    }

    private static function fetchDesignation(string $computerNo): ?string {
        $url = DESIGNATION_API_BASE . '/GetByComputeInquiry?computerNo=' . urlencode($computerNo) . '&start=1&limit=10';
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $resp = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($resp === false || $httpCode !== 200) return null;
        $data = json_decode($resp, true);
        $records = $data['Data'] ?? [];
        foreach ($records as $r) {
            $d = trim($r['EMA_DESIGNATION_STRING'] ?? '');
            if ($d !== '') return $d;
        }
        return null;
    }

    private static function applyEmployeeData(PDO $pdo, string $memberId, array $rec, ?string $designation = null): array {
        $retirementDate = isset($rec['EMA_RETIREMENT_DATE']) && $rec['EMA_RETIREMENT_DATE']
            ? date('Y-m-d', strtotime(substr($rec['EMA_RETIREMENT_DATE'], 0, 10)))
            : null;
        $nic = $rec['EMA_NIC_NO'] ?? null;
        $serviceNo = $rec['EMA_SERVICE_NO'] ?? null;
        $name = $rec['EMA_FULL_NAME'] ?? null;

        $fields = [];
        $params = [];
        if ($retirementDate) { $fields[] = 'retirement_date = ?'; $params[] = $retirementDate; }
        if ($nic) { $fields[] = 'nic = ?'; $params[] = $nic; }
        if ($serviceNo) { $fields[] = 'service_no = ?'; $params[] = $serviceNo; }
        if ($name && trim($name) !== '') { $fields[] = 'name = ?'; $params[] = trim($name); }
        if ($designation !== null && trim($designation) !== '') { $fields[] = 'designation = ?'; $params[] = trim($designation); }

        if (empty($fields)) return ['updated' => false, 'reason' => 'No retirement data available'];

        $params[] = $memberId;
        $pdo->prepare("UPDATE members SET " . implode(', ', $fields) . " WHERE id = ?")->execute($params);

        return [
            'updated' => true,
            'retirement_date' => $retirementDate,
            'nic' => $nic,
            'service_no' => $serviceNo,
            'name' => $name ? trim($name) : null,
            'designation' => $designation !== null ? trim($designation) : null,
        ];
    }

    public static function syncRetirement(string $id): void {
        AuthMiddleware::requireRole('admin');
        $pdo = getDbConnection();

        $stmt = $pdo->prepare("SELECT * FROM members WHERE id = ?");
        $stmt->execute([$id]);
        $old = $stmt->fetch();
        if (!$old) Response::error('Member not found', 404);
        if (empty($old['computer_no'])) Response::error('Member has no computer number', 400);

        $rec = self::fetchEmployee($old['computer_no']);
        if (!$rec) Response::error('No employee record found for computer number ' . $old['computer_no'], 404);

        $designation = self::fetchDesignation($old['computer_no']);
        $result = self::applyEmployeeData($pdo, $id, $rec, $designation);
        if (!$result['updated']) Response::error($result['reason'], 404);

        AuditController::log('members', $id, 'sync_retirement', $old, $result, AuthMiddleware::getUserId());

        $stmt = $pdo->prepare("SELECT * FROM members WHERE id = ?");
        $stmt->execute([$id]);
        Response::success($stmt->fetch(), 'Retirement date synced from external system');
    }

    // -----------------------------------------------------------
    // Bulk sync retirement dates for all members with a computer no
    // -----------------------------------------------------------
    public static function syncAll(): void {
        AuthMiddleware::requireRole('admin');
        $pdo = getDbConnection();
        $userId = AuthMiddleware::getUserId();

        $stmt = $pdo->query("SELECT id, name, computer_no FROM members WHERE computer_no IS NOT NULL AND computer_no <> ''");
        $members = $stmt->fetchAll();

        if (empty($members)) Response::error('No members with a computer number found', 404);

        $synced = 0;
        $failed = 0;
        $noData = 0;
        $errors = [];

        foreach ($members as $member) {
            $rec = self::fetchEmployee($member['computer_no']);
            if (!$rec) {
                $failed++;
                if (count($errors) < 20) $errors[] = ['name' => $member['name'], 'computer_no' => $member['computer_no'], 'error' => 'Not found or API error'];
                continue;
            }
            $designation = self::fetchDesignation($member['computer_no']);
            $result = self::applyEmployeeData($pdo, $member['id'], $rec, $designation);
            if ($result['updated']) {
                $synced++;
                AuditController::log('members', $member['id'], 'sync_retirement', null, $result, $userId);
            } else {
                $noData++;
            }
        }

        Response::success([
            'total' => count($members),
            'synced' => $synced,
            'failed' => $failed,
            'no_data' => $noData,
            'errors' => $errors,
        ], "Bulk sync complete: {$synced} updated, {$failed} failed");
    }
}
