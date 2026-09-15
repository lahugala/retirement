<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/Response.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/AuditController.php';

class SettingsController {

    public static function index(): void {
        AuthMiddleware::requireAnyRole(['admin']);
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("SELECT setting_key, setting_value, description, updated_at FROM settings ORDER BY setting_key");
        $stmt->execute();
        $rows = $stmt->fetchAll();
        $settings = [];
        foreach ($rows as $row) {
            $settings[$row['setting_key']] = [
                'value' => json_decode($row['setting_value'], true),
                'description' => $row['description'],
                'updated_at' => $row['updated_at'],
            ];
        }
        Response::success($settings);
    }

    public static function show(string $key): void {
        AuthMiddleware::requireAnyRole(['admin']);
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("SELECT setting_key, setting_value, description FROM settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        if (!$row) Response::error('Setting not found', 404);
        Response::success([
            'key' => $row['setting_key'],
            'value' => json_decode($row['setting_value'], true),
            'description' => $row['description'],
        ]);
    }

    public static function update(): void {
        AuthMiddleware::requireAnyRole(['admin']);
        $input = json_decode(file_get_contents('php://input'), true);
        $userId = AuthMiddleware::getUserId();
        $pdo = getDbConnection();

        if (empty($input['settings']) || !is_array($input['settings'])) {
            Response::error('Settings object required', 422);
        }

        $stmt = $pdo->prepare("UPDATE settings SET setting_value = ?, updated_by = ? WHERE setting_key = ?");
        $updated = 0;
        foreach ($input['settings'] as $key => $value) {
            $jsonValue = is_string($value) ? json_encode($value) : json_encode($value);
            $stmt->execute([$jsonValue, $userId, $key]);
            $updated += $stmt->rowCount();
        }

        AuditController::log('settings', null, 'update', null, $input['settings'], $userId);
        Response::success(['updated' => $updated], 'Settings updated');
    }
}
