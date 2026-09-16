<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/helpers/UUID.php';

$pdo = getDbConnection();

$settings = [
    ['txn_create_roles', json_encode(['admin','treasurer','organizer']), 'Roles that can create transactions'],
    ['txn_edit_roles', json_encode(['admin','treasurer']), 'Roles that can edit any transaction'],
    ['txn_submit_roles', json_encode(['admin','treasurer','organizer']), 'Roles that can submit for approval'],
    ['txn_approve_roles', json_encode(['admin','treasurer']), 'Roles that can approve/reject transactions'],
    ['txn_delete_roles', json_encode(['admin']), 'Roles that can delete transactions'],
    ['txn_organizer_default_status', json_encode('draft'), 'Default status when organizer creates transaction'],
    ['txn_treasurer_default_status', json_encode('approved'), 'Default status when treasurer creates transaction'],
    ['txn_admin_default_status', json_encode('approved'), 'Default status when admin creates transaction'],
    ['txn_auto_approve_threshold', json_encode(50000), 'Auto-approve expenses below this amount (Rs.)'],
    ['txn_require_approval', json_encode(true), 'Require approval for organizer expenses above threshold'],
];

$stmt = $pdo->prepare("INSERT IGNORE INTO settings (id, setting_key, setting_value, description) VALUES (?, ?, ?, ?)");
foreach ($settings as [$key, $value, $desc]) {
    $stmt->execute([UUID::v4(), $key, $value, $desc]);
    echo "Inserted: $key\n";
}
echo "Done.\n";
