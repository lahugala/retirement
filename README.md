# Retirement Celebration Society

A full-stack web application for managing retirement celebration events, budgets, and transactions for a membership-based society. Built with Vue 3 + Ant Design Vue on the frontend and vanilla PHP with MySQL on the backend.

## Features

- **Role-based access**: Admin, Treasurer, Organizer, Board, Member — each with granular permissions
- **Event Planning**: Quarterly periods (P1 Jan-Apr, P2 May-Aug, P3 Sep-Dec) plus custom events
- **Unified Ledger**: Income and expense transactions with category, payee, receipt uploads
- **Approval Workflow**: Expenses over Rs. 100 require board/treasurer approval; self-approval blocked
- **Duplicate Detection**: Flags potential duplicate transactions (same amount, payee, within 7 days)
- **Budget Management**: Line-item budgets per event with auto-calculated actuals
- **Reporting**: Dashboard, Income Statement, Member Contributions, Event P&L, Quarterly Summary — all exportable to CSV
- **Immutabile Audit Log**: Every create/update/approve/reject/delete action is logged
- **JWT Authentication**: Stateless token-based auth with bcrypt password hashing

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Frontend | Vue 3 (Composition API), Vite, Vue Router, Pinia |
| UI Kit | Ant Design Vue 4, @antv/g2plot (charts) |
| HTTP | Axios with Bearer token interceptor |
| Backend | Vanilla PHP (no framework), custom micro-router |
| Database | MySQL / MariaDB with PDO |
| Auth | Custom HS256 JWT, bcrypt |

## Project Structure

```
retirement/
├── backend/
│   ├── index.php              # API router (32 routes)
│   ├── .htaccess              # Apache rewrite rules
│   ├── config/
│   │   ├── app.php            # Constants & DB config
│   │   └── database.php       # PDO singleton connection
│   ├── middleware/
│   │   └── AuthMiddleware.php # JWT extraction & role checks
│   ├── helpers/
│   │   ├── Response.php       # JSON response helpers
│   │   ├── Validator.php      # Fluent validation
│   │   ├── UUID.php           # RFC 4122 v4 UUID generator
│   │   └── JWT.php            # HS256 JWT encode/decode
│   ├── controllers/
│   │   ├── AuthController.php      # login, register, me
│   │   ├── MemberController.php    # member CRUD
│   │   ├── CategoryController.php  # category CRUD
│   │   ├── EventController.php     # event lifecycle + quarter generation
│   │   ├── BudgetController.php    # line-item budgets
│   │   ├── TransactionController.php # unified ledger + approval
│   │   ├── ReportController.php    # 5 reporting endpoints
│   │   ├── UploadController.php    # file upload handler
│   │   └── AuditController.php     # audit log CRUD
│   └── uploads/               # Receipt file storage
├── frontend/
│   ├── src/
│   │   ├── main.js            # App bootstrap
│   │   ├── App.vue            # Root component
│   │   ├── components/
│   │   │   └── AppLayout.vue  # Sidebar + header shell
│   │   ├── views/
│   │   │   ├── Login.vue          # Sign in / Register
│   │   │   ├── Dashboard.vue      # Home with stats & charts
│   │   │   ├── Events.vue         # Event planning overview
│   │   │   ├── EventDetail.vue    # Single event management
│   │   │   ├── Transactions.vue   # Unified ledger
│   │   │   ├── Members.vue        # Member management
│   │   │   ├── Categories.vue     # Category management
│   │   │   ├── Reports.vue        # Reports & analytics
│   │   │   └── AuditLog.vue       # Audit trail viewer
│   │   ├── stores/
│   │   │   ├── auth.js         # Auth state & persistence
│   │   │   ├── events.js       # Event + budget state
│   │   │   ├── members.js      # Member state
│   │   │   └── transactions.js # Transaction state
│   │   ├── api/
│   │   │   └── index.js        # Axios instance & API modules
│   │   ├── router/
│   │   │   └── index.js        # Routes & navigation guard
│   │   └── utils/
│   │       └── permissions.js  # Role hierarchy & access
│   ├── vite.config.js
│   └── package.json
└── database/
    └── schema.sql              # Schema + seed data
```

## Prerequisites

- PHP 8.0+ with PDO MySQL extension
- MySQL 5.7+ or MariaDB 10.3+
- Node.js 18+ and npm
- Apache with mod_rewrite (or use PHP's built-in server)

## Setup

### 1. Database

```bash
mysql -u root -p < database/schema.sql
```

This creates the `retirement_society` database with all 6 tables and seed data (default admin user + 11 categories).

### 2. Backend

Navigate to the backend directory and start the PHP built-in server:

```bash
cd backend
php -S 0.0.0.0:8080
```

The API is now available at `http://localhost:8080/api/...`.

**Database configuration** (`backend/config/app.php`):

| Variable | Default | Environment Override |
|----------|---------|---------------------|
| `DB_HOST` | `localhost` | `DB_HOST` |
| `DB_PORT` | `3306` | `DB_PORT` |
| `DB_NAME` | `retirement_society` | `DB_NAME` |
| `DB_USER` | `root` | `DB_USER` |
| `DB_PASS` | `505974` | `DB_PASS` |

### 3. Frontend

```bash
cd frontend
npm install
npm run dev
```

The dev server starts on `http://localhost:3000` and proxies `/api` requests to the backend on `http://localhost:8080`.

## Default Login

| Email | Password | Role |
|-------|----------|------|
| admin@society.org | admin123 | Admin |

## API Overview

All endpoints return JSON. Authentication via `Authorization: Bearer <token>` header.

### Auth
| Method | Path | Description |
|--------|------|-------------|
| POST | `/api/auth/login` | Login with email/password |
| POST | `/api/auth/register` | Self-registration |
| GET | `/api/auth/me` | Current user profile |

### Members (admin only for CUD)
| Method | Path | Description |
|--------|------|-------------|
| GET | `/api/members` | Paginated list |
| POST | `/api/members` | Create member |
| GET | `/api/members/{id}` | Get member |
| PUT | `/api/members/{id}` | Update member |
| DELETE | `/api/members/{id}` | Soft-deactivate |

### Events
| Method | Path | Description |
|--------|------|-------------|
| GET | `/api/events` | List with filters (year, quarter, status) |
| POST | `/api/events` | Create event |
| POST | `/api/events/generate-quarters` | Bulk-create periods with custom labels |
| GET | `/api/events/{id}` | Event detail with budget + income/expense summaries |
| PUT | `/api/events/{id}` | Update event |
| DELETE | `/api/events/{id}` | Cancel event (soft) |
| PUT | `/api/events/{id}/status` | Change status (planned → active → completed / cancelled) |

### Budgets
| Method | Path | Description |
|--------|------|-------------|
| GET | `/api/events/{id}/budgets` | List budget lines for an event |
| POST | `/api/events/{id}/budgets` | Add budget line |
| PUT | `/api/budgets/{id}` | Update budget line |
| DELETE | `/api/budgets/{id}` | Delete budget line |

### Transactions
| Method | Path | Description |
|--------|------|-------------|
| GET | `/api/transactions` | Paginated list with filters |
| POST | `/api/transactions` | Create (with duplicate detection) |
| GET | `/api/transactions/{id}` | Get transaction |
| PUT | `/api/transactions/{id}` | Update |
| DELETE | `/api/transactions/{id}` | Soft-delete with reason |
| PUT | `/api/transactions/{id}/approve` | Approve |
| PUT | `/api/transactions/{id}/reject` | Reject with reason |

### Reports
| Method | Path | Description |
|--------|------|-------------|
| GET | `/api/reports/dashboard` | Year totals, 12-month trend, top categories |
| GET | `/api/reports/income-statement` | Income/expense by category within date range |
| GET | `/api/reports/event-profit-loss/{id}` | Event P&L with budget vs actual, income sources |
| GET | `/api/reports/member-contributions` | Contributions grouped by retiree and member |
| GET | `/api/reports/quarterly-summary` | Per-quarter summary for a given year |

### Audit Logs
| Method | Path | Description |
|--------|------|-------------|
| GET | `/api/audit-logs` | Filterable, paginated audit trail |

## Business Rules

- **Event lifecycle**: `planned` → `active` → `completed` / `cancelled`
- **Completed/cancelled events** cannot be edited; linked transactions also locked
- **Approval threshold**: Expenses > Rs. 100 require approval; creator cannot self-approve
- **Duplicate warning**: Same amount (±1), same payee, within 7 days → 409 with `confirm_duplicate`
- **Organizer scope**: Organizers only see their own events/transactions
- **Budget auto-update**: `events.budget_allocated` recalculated from budget line items
- **Soft delete**: Records are flagged `is_deleted = 1` with a reason, never permanently removed
