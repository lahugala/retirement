<?php
/**
 * Retirement Celebration Society — API Router
 */

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', '0');

// CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Autoload helpers
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/helpers/Response.php';

// Route matching
$method = $_SERVER['REQUEST_METHOD'];
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if (php_sapi_name() !== 'cli-server') {
    // Under Apache/XAMPP — derive base path from script location
    $scriptDir = dirname($_SERVER['SCRIPT_NAME']);
    $basePath = $scriptDir === '/' ? '' : $scriptDir;
} else {
    $basePath = '';
}
$uri = substr($uri, strlen($basePath));
$uri = '/' . trim($uri, '/');

// Match route patterns
$routes = [
    // Auth
    ['POST', '/api/auth/login',        'AuthController@login'],
    ['POST', '/api/auth/register',     'AuthController@register'],
    ['GET',  '/api/auth/me',           'AuthController@me'],

    // Users (auth)
    ['GET',    '/api/users',              'UserController@index'],
    ['POST',   '/api/users',              'UserController@store'],
    ['GET',    '/api/users/{id}',         'UserController@show'],
    ['PUT',    '/api/users/{id}',         'UserController@update'],
    ['DELETE', '/api/users/{id}',         'UserController@destroy'],

    // Members (retiree directory)
    ['GET',    '/api/members',            'MembersController@index'],
    ['POST',   '/api/members',            'MembersController@store'],
    ['GET',    '/api/members/{id}',       'MembersController@show'],
    ['PUT',    '/api/members/{id}',       'MembersController@update'],
    ['DELETE', '/api/members/{id}',       'MembersController@destroy'],
    ['POST',   '/api/members/{id}/sync-retirement', 'MembersController@syncRetirement'],
    ['POST',   '/api/members/sync-all',   'MembersController@syncAll'],

    // Categories
    ['GET',    '/api/categories',           'CategoryController@index'],
    ['POST',   '/api/categories',           'CategoryController@store'],
    ['GET',    '/api/categories/{id}',      'CategoryController@show'],
    ['PUT',    '/api/categories/{id}',      'CategoryController@update'],
    ['DELETE', '/api/categories/{id}',      'CategoryController@destroy'],

    // Events
    ['GET',    '/api/events',               'EventController@index'],
    ['POST',   '/api/events',               'EventController@store'],
    ['POST',   '/api/events/generate-quarters', 'EventController@generateQuarters'],
    ['GET',    '/api/events/{id}',          'EventController@show'],
    ['PUT',    '/api/events/{id}',          'EventController@update'],
    ['DELETE', '/api/events/{id}',          'EventController@destroy'],
    ['PUT',    '/api/events/{id}/status',   'EventController@updateStatus'],
    ['GET',    '/api/events/{id}/gift-issuance', 'EventController@giftIssuance'],

    // Budgets (nested under events)
    ['GET',    '/api/events/{id}/budgets',  'BudgetController@index'],
    ['POST',   '/api/events/{id}/budgets',  'BudgetController@store'],

    // Budgets (standalone)
    ['PUT',    '/api/budgets/{id}',         'BudgetController@update'],
    ['DELETE', '/api/budgets/{id}',         'BudgetController@destroy'],

    // Transactions
    ['GET',    '/api/transactions',              'TransactionController@index'],
    ['POST',   '/api/transactions',              'TransactionController@store'],
    ['GET',    '/api/transactions/{id}',         'TransactionController@show'],
    ['PUT',    '/api/transactions/{id}',         'TransactionController@update'],
    ['DELETE', '/api/transactions/{id}',         'TransactionController@destroy'],
    ['PUT',    '/api/transactions/{id}/approve', 'TransactionController@approve'],
    ['PUT',    '/api/transactions/{id}/reject',  'TransactionController@reject'],

    // Accounts (Chart of Accounts)
    ['GET',    '/api/accounts',               'AccountController@index'],
    ['POST',   '/api/accounts',               'AccountController@store'],
    ['GET',    '/api/accounts/{id}',          'AccountController@show'],
    ['PUT',    '/api/accounts/{id}',          'AccountController@update'],
    ['DELETE', '/api/accounts/{id}',          'AccountController@destroy'],

    // Journal Entries
    ['GET',    '/api/journal-entries',        'JournalEntryController@index'],
    ['GET',    '/api/journal-entries/{id}',   'JournalEntryController@show'],

    // Gift Stock
    ['GET',    '/api/gift-stock',             'GiftStockController@index'],
    ['POST',   '/api/gift-stock',             'GiftStockController@store'],
    ['GET',    '/api/gift-stock/{id}',        'GiftStockController@show'],
    ['PUT',    '/api/gift-stock/{id}',        'GiftStockController@update'],
    ['DELETE', '/api/gift-stock/{id}',        'GiftStockController@destroy'],
    ['POST',   '/api/gift-stock/{id}/receive', 'GiftStockController@receive'],
    ['POST',   '/api/gift-stock/{id}/issue',   'GiftStockController@issue'],
    ['GET',    '/api/gift-stock/{id}/movements', 'GiftStockController@movements'],
    ['PUT',    '/api/gift-stock/movements/{id}', 'GiftStockController@updateMovement'],
    ['DELETE', '/api/gift-stock/movements/{id}', 'GiftStockController@destroyMovement'],

    // Uploads
    ['POST',   '/api/upload',               'UploadController@upload'],

    // Reports
    ['GET',    '/api/reports/dashboard',               'ReportController@dashboard'],
    ['GET',    '/api/reports/income-statement',         'ReportController@incomeStatement'],
    ['GET',    '/api/reports/event-profit-loss/{id}',  'ReportController@eventProfitLoss'],
    ['GET',    '/api/reports/member-contributions',     'ReportController@memberContributions'],
    ['GET',    '/api/reports/account-balances',         'ReportController@accountBalances'],
    ['GET',    '/api/reports/trial-balance',            'ReportController@trialBalance'],

    // Audit logs
    ['GET',    '/api/reports/quarterly-summary',  'ReportController@quarterlySummary'],
    ['GET',    '/api/reports/retired-members',     'ReportController@retiredMembers'],
    ['GET',    '/api/reports/gift-history',        'ReportController@giftHistory'],
    ['GET',    '/api/reports/gift-not-issued',    'ReportController@giftNotIssued'],
    ['GET',    '/api/audit-logs',            'AuditController@index'],
];

function matchRoute(string $method, string $uri, array $routes): ?array {
    foreach ($routes as [$routeMethod, $pattern, $handler]) {
        if ($method !== $routeMethod) continue;

        // Convert {id} placeholders to regex
        $regex = preg_replace('/\{(\w+)\}/', '(?P<$1>[0-9a-fA-F-]+)', $pattern);
        $regex = '#^' . $regex . '$#';

        if (preg_match($regex, $uri, $matches)) {
            $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
            return ['handler' => $handler, 'params' => $params];
        }
    }
    return null;
}

// Serve uploaded files directly
if (preg_match('#^/uploads/(.+)$#', $uri, $m)) {
    $file = __DIR__ . '/uploads/' . basename($m[1]);
    if (file_exists($file)) {
        $ext = pathinfo($file, PATHINFO_EXTENSION);
        $mimeTypes = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'pdf' => 'application/pdf'];
        header('Content-Type: ' . ($mimeTypes[$ext] ?? 'application/octet-stream'));
        readfile($file);
        exit;
    }
    Response::error('File not found', 404);
}

$matched = matchRoute($method, $uri, $routes);

if (!$matched) {
    Response::error('Route not found: ' . $method . ' ' . $uri, 404);
}

try {
    [$controller, $action] = explode('@', $matched['handler']);
    require_once __DIR__ . "/controllers/$controller.php";
    $controller::$action(...array_values($matched['params']));
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    Response::error('A database error occurred', 500);
} catch (Throwable $e) {
    error_log("Unhandled error in {$matched['handler']}: " . $e->getMessage());
    Response::error('Internal server error', 500);
}
