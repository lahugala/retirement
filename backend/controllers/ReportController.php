<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/Response.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';

class ReportController {

    // -----------------------------------------------------------
    // Dashboard summary
    // -----------------------------------------------------------
    public static function dashboard(): void {
        AuthMiddleware::requireAnyRole(['admin', 'treasurer', 'board']);
        $pdo = getDbConnection();
        $year = $_GET['year'] ?? date('Y');
        $income = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE type = 'income' AND is_deleted = 0 AND status IN ('approved','pending_approval') AND YEAR(transaction_date) = ?");
        $income->execute([$year]);
        $totalIncome = (float)$income->fetchColumn();

        // Total expenses this year
        $expense = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE type = 'expense' AND is_deleted = 0 AND status IN ('approved','pending_approval') AND YEAR(transaction_date) = ?");
        $expense->execute([$year]);
        $totalExpense = (float)$expense->fetchColumn();

        // Pending approvals count
        $pending = $pdo->query("SELECT COUNT(*) FROM transactions WHERE status = 'pending_approval' AND is_deleted = 0")->fetchColumn();

        // Active events count
        $activeEvents = $pdo->query("SELECT COUNT(*) FROM events WHERE status IN ('planned','active')")->fetchColumn();

        // Member stats
        $totalMembers = $pdo->query("SELECT COUNT(*) FROM members")->fetchColumn();
        $retiredMembers = $pdo->query("SELECT COUNT(*) FROM members WHERE status = 'retired'")->fetchColumn();

        // Gift stock value on hand
        $giftStockValue = $pdo->query("SELECT COALESCE(SUM(quantity * unit_price), 0) FROM gift_stock WHERE is_active = 1")->fetchColumn();

        // Monthly income/expense for chart (last 12 months — fill gaps with 0)
        $rawMonthly = $pdo->query("
            SELECT DATE_FORMAT(transaction_date, '%Y-%m') AS month,
                   SUM(CASE WHEN type='income' THEN amount ELSE 0 END) AS income,
                   SUM(CASE WHEN type='expense' THEN amount ELSE 0 END) AS expense
            FROM transactions
            WHERE is_deleted = 0 AND status IN ('approved','pending_approval')
              AND transaction_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
            GROUP BY DATE_FORMAT(transaction_date, '%Y-%m')
            ORDER BY month ASC
        ")->fetchAll();

        // Index raw data by month
        $indexed = [];
        foreach ($rawMonthly as $row) {
            $indexed[$row['month']] = $row;
        }

        // Generate all 12 months and fill gaps
        $monthly = [];
        for ($i = 11; $i >= 0; $i--) {
            $month = date('Y-m', strtotime("-{$i} months"));
            $monthly[] = $indexed[$month] ?? [
                'month'   => $month,
                'income'  => '0',
                'expense' => '0',
            ];
        }

        // Top categories this year
        $topCategories = $pdo->prepare("
            SELECT c.name, c.type, SUM(t.amount) AS total
            FROM transactions t
            JOIN categories c ON t.category_id = c.id
            WHERE t.is_deleted = 0 AND t.status IN ('approved','pending_approval') AND YEAR(t.transaction_date) = ?
            GROUP BY c.id, c.name, c.type
            ORDER BY total DESC
            LIMIT 10
        ");
        $topCategories->execute([$year]);

        // Yearly retired member count (all years with data)
        $yearlyRetirements = $pdo->query("
            SELECT YEAR(retirement_date) AS year, COUNT(*) AS count
            FROM members
            WHERE retirement_date IS NOT NULL
            GROUP BY YEAR(retirement_date)
            ORDER BY year ASC
        ")->fetchAll();

        Response::success([
            'total_income'     => $totalIncome,
            'total_expense'    => $totalExpense,
            'net_balance'      => $totalIncome - $totalExpense,
            'pending_approvals' => (int)$pending,
            'active_events'    => (int)$activeEvents,
            'total_members'    => (int)$totalMembers,
            'retired_members'  => (int)$retiredMembers,
            'gift_stock_value' => round((float)$giftStockValue, 2),
            'monthly_trend'    => $monthly,
            'yearly_retirements' => $yearlyRetirements,
            'top_categories'   => $topCategories->fetchAll(),
        ]);
    }

    // -----------------------------------------------------------
    // Income Statement
    // -----------------------------------------------------------
    public static function incomeStatement(): void {
        AuthMiddleware::requireAnyRole(['admin', 'treasurer', 'board']);
        $pdo = getDbConnection();

        $dateFrom = $_GET['date_from'] ?? date('Y-01-01');
        $dateTo = $_GET['date_to'] ?? date('Y-m-d');

        $stmt = $pdo->prepare("
            SELECT c.id, c.name, c.type, COALESCE(SUM(t.amount), 0) AS total, COUNT(t.id) AS count
            FROM categories c
            LEFT JOIN transactions t ON c.id = t.category_id
                AND t.is_deleted = 0 AND t.status IN ('approved','pending_approval')
                AND t.transaction_date BETWEEN ? AND ?
            WHERE c.is_active = 1
            GROUP BY c.id, c.name, c.type
            ORDER BY c.type, c.name
        ");
        $stmt->execute([$dateFrom, $dateTo]);
        $categories = $stmt->fetchAll();

        $incomeTotal = 0;
        $expenseTotal = 0;
        foreach ($categories as &$cat) {
            if ($cat['type'] === 'income') $incomeTotal += (float)$cat['total'];
            else $expenseTotal += (float)$cat['total'];
        }

        Response::success([
            'date_from'    => $dateFrom,
            'date_to'      => $dateTo,
            'categories'   => $categories,
            'total_income' => $incomeTotal,
            'total_expense' => $expenseTotal,
            'net_income'   => $incomeTotal - $expenseTotal,
        ]);
    }

    // -----------------------------------------------------------
    // Event Profit & Loss
    // -----------------------------------------------------------
    public static function eventProfitLoss(string $eventId): void {
        AuthMiddleware::requireAnyRole(['admin', 'treasurer', 'board', 'organizer']);
        $pdo = getDbConnection();

        $stmt = $pdo->prepare("SELECT * FROM events WHERE id = ?");
        $stmt->execute([$eventId]);
        $event = $stmt->fetch();
        if (!$event) Response::error('Event not found', 404);

        // Budget vs actual
        $budgets = $pdo->prepare("
            SELECT b.*, c.name AS category_name,
                   COALESCE(SUM(CASE WHEN t.type = 'expense' AND t.status IN ('approved','pending_approval') AND t.is_deleted = 0 THEN t.amount ELSE 0 END), 0) AS actual
            FROM budgets b
            JOIN categories c ON b.category_id = c.id
            LEFT JOIN transactions t ON t.event_id = b.event_id AND t.category_id = b.category_id
            WHERE b.event_id = ?
            GROUP BY b.id, c.name
        ");
        $budgets->execute([$eventId]);
        $budgetLines = $budgets->fetchAll();

        // Income collected for event (total + by category)
        $incomeStmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE event_id = ? AND type = 'income' AND is_deleted = 0 AND status IN ('approved','pending_approval')");
        $incomeStmt->execute([$eventId]);
        $totalIncome = (float)$incomeStmt->fetchColumn();

        $incomeByCat = $pdo->prepare("
            SELECT c.id AS category_id, c.name AS category_name, COALESCE(SUM(t.amount), 0) AS amount
            FROM transactions t
            JOIN categories c ON t.category_id = c.id
            WHERE t.event_id = ? AND t.type = 'income' AND t.is_deleted = 0 AND t.status IN ('approved','pending_approval')
            GROUP BY c.id, c.name
            ORDER BY amount DESC
        ");
        $incomeByCat->execute([$eventId]);
        $incomeLines = $incomeByCat->fetchAll();

        // Total expense
        $expenseStmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE event_id = ? AND type = 'expense' AND is_deleted = 0 AND status IN ('approved','pending_approval')");
        $expenseStmt->execute([$eventId]);
        $totalExpense = (float)$expenseStmt->fetchColumn();

        $budgetTotal = (float)$event['budget_allocated'];

        // Non-budgeted expenses (expense transactions not linked to a budget line)
        $unbudgetedExpense = $pdo->prepare("
            SELECT COALESCE(SUM(amount), 0) FROM transactions t
            LEFT JOIN budgets b ON b.event_id = t.event_id AND b.category_id = t.category_id
            WHERE t.event_id = ? AND t.type = 'expense' AND t.is_deleted = 0 AND t.status IN ('approved','pending_approval')
              AND b.id IS NULL
        ");
        $unbudgetedExpense->execute([$eventId]);
        $unbudgetedTotal = (float)$unbudgetedExpense->fetchColumn();

        $budgetUtilization = $budgetTotal > 0 ? round(($totalExpense / $budgetTotal) * 100, 1) : 0;

        Response::success([
            'event'             => $event,
            'budget_lines'      => $budgetLines,
            'budget_total'      => $budgetTotal,
            'total_income'      => $totalIncome,
            'income_lines'      => $incomeLines,
            'total_expense'     => $totalExpense,
            'unbudgeted_expense'=> $unbudgetedTotal,
            'variance'          => $budgetTotal - $totalExpense,
            'net_result'        => $totalIncome - $totalExpense,
            'budget_utilization'=> $budgetUtilization,
        ]);
    }

    // -----------------------------------------------------------
    // Member Contributions (grouped by retiree)
    // -----------------------------------------------------------
    public static function memberContributions(): void {
        AuthMiddleware::requireAnyRole(['admin', 'treasurer', 'board']);
        $pdo = getDbConnection();

        $dateFrom = $_GET['date_from'] ?? date('Y-01-01');
        $dateTo = $_GET['date_to'] ?? date('Y-m-d');

        // By retiree
        $byRetiree = $pdo->prepare("
            SELECT COALESCE(designated_retiree, 'General Fund') AS retiree,
                   COUNT(*) AS count,
                   SUM(amount) AS total
            FROM transactions
            WHERE type = 'income' AND is_deleted = 0 AND status IN ('approved','pending_approval')
              AND transaction_date BETWEEN ? AND ?
            GROUP BY designated_retiree
            ORDER BY total DESC
        ");
        $byRetiree->execute([$dateFrom, $dateTo]);

        // By member
        $byMember = $pdo->prepare("
            SELECT m.name AS member_name, COUNT(t.id) AS count, SUM(t.amount) AS total
            FROM transactions t
            JOIN users m ON t.created_by = m.id
            WHERE t.type = 'income' AND t.is_deleted = 0 AND t.status IN ('approved','pending_approval')
              AND t.transaction_date BETWEEN ? AND ?
            GROUP BY m.id, m.name
            ORDER BY total DESC
        ");
        $byMember->execute([$dateFrom, $dateTo]);

        // Grand total
        $totalStmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE type = 'income' AND is_deleted = 0 AND status IN ('approved','pending_approval') AND transaction_date BETWEEN ? AND ?");
        $totalStmt->execute([$dateFrom, $dateTo]);
        $grandTotal = (float)$totalStmt->fetchColumn();

        Response::success([
            'date_from'   => $dateFrom,
            'date_to'     => $dateTo,
            'by_retiree'  => $byRetiree->fetchAll(),
            'by_member'   => $byMember->fetchAll(),
            'grand_total' => $grandTotal,
        ]);
    }

    // -----------------------------------------------------------
    // Quarterly Summary
    // -----------------------------------------------------------
    public static function quarterlySummary(): void {
        AuthMiddleware::requireAnyRole(['admin', 'treasurer', 'board']);
        $pdo = getDbConnection();
        $year = (int)($_GET['year'] ?? date('Y'));

        // All events for the year
        $quarters = $pdo->prepare("
            SELECT e.id, e.quarter, e.name, e.budget_allocated, e.status, e.retiree_name,
                   COALESCE(inc.income, 0) AS total_income,
                   COALESCE(exp.expense, 0) AS total_expense
            FROM events e
            LEFT JOIN (
                SELECT event_id, SUM(amount) AS income FROM transactions
                WHERE type = 'income' AND is_deleted = 0 AND status IN ('approved','pending_approval')
                GROUP BY event_id
            ) inc ON inc.event_id = e.id
            LEFT JOIN (
                SELECT event_id, SUM(amount) AS expense FROM transactions
                WHERE type = 'expense' AND is_deleted = 0 AND status IN ('approved','pending_approval')
                GROUP BY event_id
            ) exp ON exp.event_id = e.id
            WHERE e.year = ?
            ORDER BY e.quarter ASC
        ");
        $quarters->execute([$year]);

        $yearTotalIncome = 0;
        $yearTotalExpense = 0;
        $yearBudget = 0;
        $rows = $quarters->fetchAll();
        foreach ($rows as &$q) {
            $yearTotalIncome += (float)$q['total_income'];
            $yearTotalExpense += (float)$q['total_expense'];
            $yearBudget += (float)$q['budget_allocated'];
        }

        Response::success([
            'year'             => $year,
            'quarters'         => $rows,
            'year_total_income'  => $yearTotalIncome,
            'year_total_expense' => $yearTotalExpense,
            'year_budget'        => $yearBudget,
            'year_variance'      => $yearBudget - $yearTotalExpense,
            'year_net'           => $yearTotalIncome - $yearTotalExpense,
        ]);
    }

    // -----------------------------------------------------------
    // Account Balances (from journal entries)
    // -----------------------------------------------------------
    public static function accountBalances(): void {
        AuthMiddleware::requireAnyRole(['admin', 'treasurer', 'board']);
        $pdo = getDbConnection();

        $dateFrom = $_GET['date_from'] ?? null;
        $dateTo = $_GET['date_to'] ?? null;

        $where = '';
        $params = [];
        if ($dateFrom) { $where .= " AND je.entry_date >= ?"; $params[] = $dateFrom; }
        if ($dateTo) { $where .= " AND je.entry_date <= ?"; $params[] = $dateTo; }

        $sql = "SELECT a.id, a.code, a.name, a.type,
                       COALESCE(SUM(jl.debit), 0) AS total_debit,
                       COALESCE(SUM(jl.credit), 0) AS total_credit
                FROM accounts a
                LEFT JOIN journal_lines jl ON jl.account_id = a.id
                LEFT JOIN journal_entries je ON jl.journal_entry_id = je.id
                WHERE a.is_active = 1 $where
                GROUP BY a.id, a.code, a.name, a.type
                ORDER BY a.type, a.code";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $accounts = $stmt->fetchAll();

        $totalAssets = 0; $totalLiabilities = 0; $totalEquity = 0;
        $totalIncome = 0; $totalExpense = 0;

        foreach ($accounts as &$acct) {
            $acct['balance'] = (float)$acct['total_debit'] - (float)$acct['total_credit'];
            // Normal balance: Asset/Expense = Debit; Liability/Equity/Income = Credit
            $normalBalance = in_array($acct['type'], ['asset', 'expense']) ? 'debit' : 'credit';
            $acct['normal_balance'] = $normalBalance;
            switch ($acct['type']) {
                case 'asset': $totalAssets += $acct['balance']; break;
                case 'liability': $totalLiabilities += abs($acct['balance']); break;
                case 'equity': $totalEquity += abs($acct['balance']); break;
                case 'income': $totalIncome += abs($acct['balance']); break;
                case 'expense': $totalExpense += $acct['balance']; break;
            }
        }

        // For income/expense accounts, balance is credit - debit (normal credit balance)
        // Net income = total income (credit) - total expense (debit)
        $netIncome = $totalIncome - $totalExpense;

        Response::success([
            'accounts' => $accounts,
            'totals' => [
                'total_assets' => $totalAssets,
                'total_liabilities' => $totalLiabilities,
                'total_equity' => $totalEquity + $netIncome, // include current period net income
                'total_income' => $totalIncome,
                'total_expense' => $totalExpense,
                'net_income' => $netIncome,
            ],
        ]);
    }

    // -----------------------------------------------------------
    // Trial Balance
    // -----------------------------------------------------------
    public static function trialBalance(): void {
        AuthMiddleware::requireAnyRole(['admin', 'treasurer', 'board']);
        $pdo = getDbConnection();

        $dateFrom = $_GET['date_from'] ?? null;
        $dateTo = $_GET['date_to'] ?? null;

        $where = '';
        $params = [];
        if ($dateFrom) { $where .= " AND je.entry_date >= ?"; $params[] = $dateFrom; }
        if ($dateTo) { $where .= " AND je.entry_date <= ?"; $params[] = $dateTo; }

        $sql = "SELECT a.code, a.name AS account_name, a.type,
                       COALESCE(SUM(jl.debit), 0) AS total_debit,
                       COALESCE(SUM(jl.credit), 0) AS total_credit
                FROM accounts a
                LEFT JOIN journal_lines jl ON jl.account_id = a.id
                LEFT JOIN journal_entries je ON jl.journal_entry_id = je.id
                WHERE a.is_active = 1 $where
                GROUP BY a.code, a.name, a.type
                ORDER BY a.type, a.code";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $totalDebit = 0; $totalCredit = 0;
        foreach ($rows as &$r) {
            $totalDebit += (float)$r['total_debit'];
            $totalCredit += (float)$r['total_credit'];
        }

        Response::success([
            'lines' => $rows,
            'total_debit' => $totalDebit,
            'total_credit' => $totalCredit,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
        ]);
    }

    // -----------------------------------------------------------
    // Gift History (stock movements)
    // -----------------------------------------------------------
    public static function giftHistory(): void {
        AuthMiddleware::requireAnyRole(['admin', 'treasurer', 'board']);
        $pdo = getDbConnection();

        $dateFrom = $_GET['date_from'] ?? null;
        $dateTo = $_GET['date_to'] ?? null;
        $giftId = $_GET['gift_id'] ?? null;

        $where = '';
        $params = [];
        if ($dateFrom) { $where .= " AND m.created_at >= ?"; $params[] = $dateFrom . ' 00:00:00'; }
        if ($dateTo) { $where .= " AND m.created_at <= ?"; $params[] = $dateTo . ' 23:59:59'; }
        if ($giftId) { $where .= " AND m.gift_id = ?"; $params[] = $giftId; }

        $stmt = $pdo->prepare("
            SELECT m.id, m.gift_id, g.name AS gift_name, g.unit, m.movement_type,
                   m.quantity, m.unit_price, m.event_id, e.name AS event_name,
                   m.member_id, mb.name AS member_name, m.notes,
                   u.name AS created_by_name, m.created_at
            FROM gift_stock_movements m
            LEFT JOIN gift_stock g ON m.gift_id = g.id
            LEFT JOIN events e ON m.event_id = e.id
            LEFT JOIN members mb ON m.member_id = mb.id
            LEFT JOIN users u ON m.created_by = u.id
            WHERE 1=1 $where
            ORDER BY m.created_at DESC
        ");
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        foreach ($rows as &$r) {
            $r['total_value'] = round($r['quantity'] * $r['unit_price'], 2);
        }

        $totalReceived = 0;
        $totalIssued = 0;
        $valueReceived = 0;
        $valueIssued = 0;
        foreach ($rows as $r) {
            if ($r['movement_type'] === 'received') {
                $totalReceived += (int)$r['quantity'];
                $valueReceived += (float)$r['total_value'];
            } else {
                $totalIssued += (int)$r['quantity'];
                $valueIssued += (float)$r['total_value'];
            }
        }

        Response::success([
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'movements' => $rows,
            'summary' => [
                'total_received' => $totalReceived,
                'total_issued' => $totalIssued,
                'value_received' => round($valueReceived, 2),
                'value_issued' => round($valueIssued, 2),
            ],
        ]);
    }

    // -----------------------------------------------------------
    // Gift Not Issued (event retirees without gifts)
    // -----------------------------------------------------------
    public static function giftNotIssued(): void {
        AuthMiddleware::requireAnyRole(['admin', 'treasurer', 'board']);
        $pdo = getDbConnection();

        $eventId = $_GET['event_id'] ?? null;

        $events = [];
        if ($eventId) {
            $stmt = $pdo->prepare("SELECT * FROM events WHERE id = ?");
            $stmt->execute([$eventId]);
            $event = $stmt->fetch();
            if (!$event) Response::error('Event not found', 404);
            $events[] = $event;
        } else {
            $events = $pdo->query("SELECT * FROM events ORDER BY name ASC")->fetchAll();
        }

        // All issued movements grouped by event + member
        $issuedByEventMember = [];
        $stmt = $pdo->query("SELECT event_id, member_id, COUNT(*) AS issued FROM gift_stock_movements
                             WHERE movement_type = 'issued' AND member_id IS NOT NULL
                             GROUP BY event_id, member_id");
        foreach ($stmt->fetchAll() as $row) {
            $issuedByEventMember[$row['event_id']][$row['member_id']] = (int)$row['issued'];
        }

        $eventRetirees = [];
        $notIssuedCount = 0;

        foreach ($events as $event) {
            $names = array_values(array_filter(array_map('trim', explode(',', $event['retiree_name'] ?? ''))));

            $members = [];
            if (count($names) > 0) {
                $placeholders = implode(',', array_fill(0, count($names), '?'));
                $stmt = $pdo->prepare("SELECT * FROM members WHERE name IN ($placeholders)");
                $stmt->execute($names);
                foreach ($stmt->fetchAll() as $m) {
                    $members[strtolower(trim($m['name']))] = $m;
                }
            }

            $issuedByMember = $issuedByEventMember[$event['id']] ?? [];

            foreach ($names as $name) {
                $member = $members[strtolower(trim($name))] ?? null;
                $memberId = $member['id'] ?? null;
                $issued = $memberId ? ($issuedByMember[$memberId] ?? 0) : 0;
                $eventRetirees[] = [
                    'event_id' => $event['id'],
                    'event_name' => $event['name'],
                    'name' => trim($name),
                    'member_id' => $memberId,
                    'gift_issued' => $issued > 0,
                    'issued_count' => $issued,
                    'nic' => $member['nic'] ?? null,
                    'service_no' => $member['service_no'] ?? null,
                    'computer_no' => $member['computer_no'] ?? null,
                    'matched' => $memberId !== null,
                ];
                if ($issued === 0) $notIssuedCount++;
            }
        }

        Response::success([
            'event' => $eventId ? ($events[0] ?? null) : null,
            'event_retirees' => $eventRetirees,
            'not_issued_count' => $notIssuedCount,
        ]);
    }

    // -----------------------------------------------------------
    // Retired Members (filtered by retirement_date range)
    // -----------------------------------------------------------
    public static function retiredMembers(): void {
        AuthMiddleware::requireAnyRole(['admin', 'treasurer', 'board']);
        $pdo = getDbConnection();

        $dateFrom = $_GET['date_from'] ?? date('Y-01-01');
        $dateTo   = $_GET['date_to'] ?? date('Y-m-d');

        $stmt = $pdo->prepare("SELECT name, nic, service_no, computer_no, retirement_date
                               FROM members
                               WHERE retirement_date IS NOT NULL
                                 AND retirement_date BETWEEN ? AND ?
                               ORDER BY retirement_date ASC");
        $stmt->execute([$dateFrom, $dateTo]);
        $rows = $stmt->fetchAll();

        Response::success([
            'date_from' => $dateFrom,
            'date_to'   => $dateTo,
            'members'   => $rows,
            'total'     => count($rows),
        ]);
    }
}
