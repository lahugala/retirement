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
    computer_no VARCHAR(50) DEFAULT NULL,
    retirement_date DATE DEFAULT NULL,
    status ENUM('active','retired','deceased') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- -----------------------------------------------------------
-- 4. EVENTS
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS events (
    id CHAR(36) PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    retiree_name VARCHAR(255) DEFAULT NULL,
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
-- 8. SEED DATA
-- -----------------------------------------------------------

-- Default admin (password: password)
INSERT INTO users (id, name, email, password, role) VALUES
('a0000000-0000-0000-0000-000000000001', 'System Admin', 'admin@society.org',
 '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');

-- Income categories
INSERT INTO categories (id, type, name) VALUES
('c0010000-0000-0000-0000-000000000001', 'income', 'Member Dues'),
('c0010000-0000-0000-0000-000000000002', 'income', 'Donations'),
('c0010000-0000-0000-0000-000000000003', 'income', 'Event Ticket Sales'),
('c0010000-0000-0000-0000-000000000004', 'income', 'Sponsorships'),
('c0010000-0000-0000-0000-000000000005', 'income', 'Refunds');

-- Expense categories
INSERT INTO categories (id, type, name) VALUES
('c0020000-0000-0000-0000-000000000001', 'expense', 'Venue'),
('c0020000-0000-0000-0000-000000000002', 'expense', 'Catering'),
('c0020000-0000-0000-0000-000000000003', 'expense', 'Gift'),
('c0020000-0000-0000-0000-000000000004', 'expense', 'Decoration'),
('c0020000-0000-0000-0000-000000000005', 'expense', 'Printing'),
('c0020000-0000-0000-0000-000000000006', 'expense', 'Miscellaneous');
