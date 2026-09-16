-- =============================================================
-- Retirement Celebration Society — Database Schema
-- Engine: MySQL / MariaDB
-- =============================================================

CREATE DATABASE IF NOT EXISTS retirement_society
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE retirement_society;

-- -----------------------------------------------------------
-- 1. USERS (authentication & profiles)
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id CHAR(36) PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin','treasurer','organizer','board','member') NOT NULL DEFAULT 'member',
    join_date DATE DEFAULT (CURRENT_DATE),
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- -----------------------------------------------------------
-- 2. CATEGORIES (Income / Expense types)
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS categories (
    id CHAR(36) PRIMARY KEY,
    type ENUM('income','expense') NOT NULL,
    name VARCHAR(255) NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- -----------------------------------------------------------
-- 3. MEMBERS (retirees / society members directory)
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS members (
    id CHAR(36) PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    nic VARCHAR(20) DEFAULT NULL,
    service_no VARCHAR(50) DEFAULT NULL,
    designation VARCHAR(255) DEFAULT NULL,
    computer_no VARCHAR(50) DEFAULT NULL,
    retirement_date DATE DEFAULT NULL,
    status ENUM('active','retired','deceased','resigned','inactive','dismissed') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- -----------------------------------------------------------
-- 4. EVENTS
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS events (
    id CHAR(36) PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    retiree_name TEXT DEFAULT NULL,
    event_date DATE DEFAULT NULL,
    quarter TINYINT(1) DEFAULT NULL COMMENT '1=Jan-Apr, 2=May-Aug, 3=Sep-Dec',
    year YEAR DEFAULT NULL,
    budget_allocated DECIMAL(12,2) DEFAULT 0.00,
    status ENUM('planned','active','completed','cancelled') DEFAULT 'planned',
    organizer_id CHAR(36) NOT NULL,
    notes TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_event_organizer FOREIGN KEY (organizer_id) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE INDEX idx_events_quarter_year ON events(quarter, year);

-- -----------------------------------------------------------
-- 5. BUDGETS (line-item budgets per event)
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS budgets (
    id CHAR(36) PRIMARY KEY,
    event_id CHAR(36) NOT NULL,
    category_id CHAR(36) NOT NULL,
    planned_amount DECIMAL(12,2) DEFAULT 0.00,
    actual_spent DECIMAL(12,2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_budget_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    CONSTRAINT fk_budget_category FOREIGN KEY (category_id) REFERENCES categories(id)
) ENGINE=InnoDB;

-- -----------------------------------------------------------
-- 6. TRANSACTIONS (income & expense — unified ledger)
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS transactions (
    id CHAR(36) PRIMARY KEY,
    type ENUM('income','expense') NOT NULL,
    event_id CHAR(36) DEFAULT NULL,
    category_id CHAR(36) NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    transaction_date DATE NOT NULL,
    payee VARCHAR(255) DEFAULT NULL,
    description TEXT DEFAULT NULL,
    payment_method ENUM('cash','bank_transfer','credit_card','cheque','upi') DEFAULT 'cash',
    receipt_path VARCHAR(500) DEFAULT NULL,
    designated_retiree VARCHAR(255) DEFAULT NULL,
    created_by CHAR(36) NOT NULL,
    approved_by CHAR(36) DEFAULT NULL,
    status ENUM('draft','pending_approval','approved','rejected') DEFAULT 'draft',
    duplicate_of CHAR(36) DEFAULT NULL,
    is_deleted TINYINT(1) DEFAULT 0,
    delete_reason TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_txn_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE SET NULL,
    CONSTRAINT fk_txn_category FOREIGN KEY (category_id) REFERENCES categories(id),
    CONSTRAINT fk_txn_created_by FOREIGN KEY (created_by) REFERENCES users(id),
    CONSTRAINT fk_txn_approved_by FOREIGN KEY (approved_by) REFERENCES users(id),
    CONSTRAINT fk_txn_duplicate FOREIGN KEY (duplicate_of) REFERENCES transactions(id)
) ENGINE=InnoDB;

CREATE INDEX idx_txn_type ON transactions(type);
CREATE INDEX idx_txn_status ON transactions(status);
CREATE INDEX idx_txn_date ON transactions(transaction_date);
CREATE INDEX idx_txn_event ON transactions(event_id);
CREATE INDEX idx_txn_deleted ON transactions(is_deleted);

-- -----------------------------------------------------------
-- 7. AUDIT LOG (immutable)
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS audit_log (
    id CHAR(36) PRIMARY KEY,
    entity_type VARCHAR(50) NOT NULL,
    entity_id CHAR(36) NOT NULL,
    action VARCHAR(50) NOT NULL,
    old_values JSON DEFAULT NULL,
    new_values JSON DEFAULT NULL,
    changed_by CHAR(36) NOT NULL,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_audit_user FOREIGN KEY (changed_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE INDEX idx_audit_entity ON audit_log(entity_type, entity_id);
CREATE INDEX idx_audit_timestamp ON audit_log(timestamp);

-- -----------------------------------------------------------
-- 8. ACCOUNTS (Chart of Accounts)
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS accounts (
    id CHAR(36) PRIMARY KEY,
    code VARCHAR(20) NOT NULL UNIQUE,
    name VARCHAR(255) NOT NULL,
    type ENUM('asset','liability','equity','income','expense') NOT NULL,
    description TEXT DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Link categories to accounts
ALTER TABLE categories ADD COLUMN default_account_id CHAR(36) DEFAULT NULL AFTER is_active;
ALTER TABLE categories ADD CONSTRAINT fk_cat_account FOREIGN KEY (default_account_id) REFERENCES accounts(id);

-- -----------------------------------------------------------
-- 9. JOURNAL ENTRIES (double-entry headers)
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS journal_entries (
    id CHAR(36) PRIMARY KEY,
    entry_date DATE NOT NULL,
    ref_num VARCHAR(50) NOT NULL UNIQUE,
    description TEXT DEFAULT NULL,
    reference_type VARCHAR(50) DEFAULT NULL COMMENT 'transaction, manual',
    reference_id CHAR(36) DEFAULT NULL,
    created_by CHAR(36) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_je_created_by FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE INDEX idx_je_date ON journal_entries(entry_date);
CREATE INDEX idx_je_ref ON journal_entries(reference_type, reference_id);

-- -----------------------------------------------------------
-- 10. JOURNAL LINES (debit/credit entries)
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS journal_lines (
    id CHAR(36) PRIMARY KEY,
    journal_entry_id CHAR(36) NOT NULL,
    account_id CHAR(36) NOT NULL,
    debit DECIMAL(12,2) DEFAULT 0.00,
    credit DECIMAL(12,2) DEFAULT 0.00,
    description TEXT DEFAULT NULL,
    CONSTRAINT fk_jl_entry FOREIGN KEY (journal_entry_id) REFERENCES journal_entries(id) ON DELETE CASCADE,
    CONSTRAINT fk_jl_account FOREIGN KEY (account_id) REFERENCES accounts(id)
) ENGINE=InnoDB;

CREATE INDEX idx_jl_entry ON journal_lines(journal_entry_id);
CREATE INDEX idx_jl_account ON journal_lines(account_id);

-- -----------------------------------------------------------
-- 11. GIFT STOCK (inventory master + received/issued movements)
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS gift_stock (
    id CHAR(36) PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    unit VARCHAR(50) DEFAULT 'piece',
    unit_price DECIMAL(12,2) DEFAULT 0.00,
    quantity INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS gift_stock_movements (
    id CHAR(36) PRIMARY KEY,
    gift_id CHAR(36) NOT NULL,
    movement_type ENUM('received','issued') NOT NULL,
    quantity INT NOT NULL,
    unit_price DECIMAL(12,2) DEFAULT 0.00,
    event_id CHAR(36) DEFAULT NULL,
    member_id CHAR(36) DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    created_by CHAR(36) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_gsm_gift FOREIGN KEY (gift_id) REFERENCES gift_stock(id) ON DELETE CASCADE,
    CONSTRAINT fk_gsm_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE SET NULL,
    CONSTRAINT fk_gsm_member FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE SET NULL,
    CONSTRAINT fk_gsm_created_by FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE INDEX idx_gsm_gift ON gift_stock_movements(gift_id);
CREATE INDEX idx_gsm_type ON gift_stock_movements(movement_type);

-- -----------------------------------------------------------
-- 12. SETTINGS (key-value application config)
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS settings (
    id CHAR(36) PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT DEFAULT NULL,
    description TEXT DEFAULT NULL,
    updated_by CHAR(36) DEFAULT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- -----------------------------------------------------------
-- 13. SEED DATA
-- -----------------------------------------------------------

-- Default admin (password: password)
INSERT INTO users (id, name, email, password, role) VALUES
('a0000000-0000-0000-0000-000000000001', 'System Admin', 'admin@society.org',
 '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');

-- Default settings
INSERT INTO settings (id, setting_key, setting_value, description) VALUES
('0c41b1b3-6b61-4950-8929-59a77341e127', 'txn_create_roles', '["admin","treasurer","organizer"]', 'Roles that can create transactions'),
('3271ab78-c28f-4e1a-90d6-60b560c4b927', 'txn_treasurer_default_status', '"approved"', 'Default status when treasurer creates transaction'),
('363d106f-2dbf-411a-96d9-d83f522fa294', 'txn_organizer_default_status', '"draft"', 'Default status when organizer creates transaction'),
('52dde4f8-cd35-408b-a8aa-cdd13b91b287', 'txn_delete_roles', '["admin"]', 'Roles that can delete transactions'),
('7aba2787-2017-4495-b211-a1ae377c5887', 'txn_edit_roles', '["admin","treasurer"]', 'Roles that can edit any transaction'),
('85fe16d5-ad56-4f2f-8766-93c1f28c19a3', 'txn_submit_roles', '["admin","treasurer"]', 'Roles that can submit for approval'),
('87bbce47-298b-43ca-9ee6-bd2301983bbe', 'txn_approve_roles', '["admin","treasurer"]', 'Roles that can approve/reject transactions'),
('8f096469-2d76-4197-aa22-bff8b4320b47', 'txn_require_approval', 'true', 'Require approval for organizer expenses above threshold'),
('bd9b8b75-5040-4d60-8d46-230d2d1018f2', 'txn_auto_approve_threshold', '50000', 'Auto-approve expenses below this amount (Rs.)'),
('fcb30fa4-33c3-4f5f-a6ee-8a1347bfc68e', 'txn_admin_default_status', '"approved"', 'Default status when admin creates transaction');

-- Chart of Accounts
INSERT INTO accounts (id, code, name, type, description) VALUES
('a1000000-0000-0000-0000-000000000001', '1001', 'Cash', 'asset', 'Cash on hand'),
('a1000000-0000-0000-0000-000000000002', '1002', 'Bank Account', 'asset', 'Bank current/savings account'),
('a2000000-0000-0000-0000-000000000001', '2001', 'Accounts Payable', 'liability', 'Amounts owed to vendors'),
('a3000000-0000-0000-0000-000000000001', '3001', 'Retained Earnings', 'equity', 'Accumulated retained earnings'),
('a3000000-0000-0000-0000-000000000002', '3002', 'Opening Balance', 'equity', 'Opening balance adjustment'),
('a4000000-0000-0000-0000-000000000001', '4001', 'Member Dues', 'income', 'Income from member subscription dues'),
('a4000000-0000-0000-0000-000000000002', '4002', 'Donations', 'income', 'Donation income'),
('a4000000-0000-0000-0000-000000000003', '4003', 'Event Ticket Sales', 'income', 'Ticket sales income'),
('a4000000-0000-0000-0000-000000000004', '4004', 'Sponsorships', 'income', 'Sponsorship income'),
('a4000000-0000-0000-0000-000000000005', '4005', 'Refunds', 'income', 'Refund income'),
('a4000000-0000-0000-0000-000000000006', '4006', 'Bank Interest', 'income', 'Interest earned on bank deposits'),
('a5000000-0000-0000-0000-000000000001', '5001', 'Venue', 'expense', 'Venue rental expense'),
('a5000000-0000-0000-0000-000000000002', '5002', 'Catering', 'expense', 'Catering expense'),
('a5000000-0000-0000-0000-000000000003', '5003', 'Gift', 'expense', 'Gift expense'),
('a5000000-0000-0000-0000-000000000004', '5004', 'Decoration', 'expense', 'Decoration expense'),
('a5000000-0000-0000-0000-000000000005', '5005', 'Printing', 'expense', 'Printing expense'),
('a5000000-0000-0000-0000-000000000006', '5006', 'Miscellaneous', 'expense', 'Miscellaneous expense');

-- Income categories
INSERT INTO categories (id, type, name, default_account_id) VALUES
('c0010000-0000-0000-0000-000000000001', 'income', 'Member Dues', 'a4000000-0000-0000-0000-000000000001'),
('c0010000-0000-0000-0000-000000000002', 'income', 'Donations', 'a4000000-0000-0000-0000-000000000002'),
('c0010000-0000-0000-0000-000000000003', 'income', 'Event Ticket Sales', 'a4000000-0000-0000-0000-000000000003'),
('c0010000-0000-0000-0000-000000000004', 'income', 'Sponsorships', 'a4000000-0000-0000-0000-000000000004'),
('c0010000-0000-0000-0000-000000000005', 'income', 'Refunds', 'a4000000-0000-0000-0000-000000000005');

-- Expense categories
INSERT INTO categories (id, type, name, default_account_id) VALUES
('c0020000-0000-0000-0000-000000000001', 'expense', 'Venue', 'a5000000-0000-0000-0000-000000000001'),
('c0020000-0000-0000-0000-000000000002', 'expense', 'Catering', 'a5000000-0000-0000-0000-000000000002'),
('c0020000-0000-0000-0000-000000000003', 'expense', 'Gift', 'a5000000-0000-0000-0000-000000000003'),
('c0020000-0000-0000-0000-000000000004', 'expense', 'Decoration', 'a5000000-0000-0000-0000-000000000004'),
('c0020000-0000-0000-0000-000000000005', 'expense', 'Printing', 'a5000000-0000-0000-0000-000000000005'),
('c0020000-0000-0000-0000-000000000006', 'expense', 'Miscellaneous', 'a5000000-0000-0000-0000-000000000006');


