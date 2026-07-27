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

        Response::success([
            'total_income'     => $totalIncome,
            'total_expense'    => $totalExpense,
            'net_balance'      => $totalIncome - $totalExpense,
            'pending_approvals' => (int)$pending,
            'active_events'    => (int)$activeEvents,
            'monthly_trend'    => $monthly,
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
