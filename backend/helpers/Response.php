<?php
class Response {
    public static function json($data, int $status = 200): void {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function success($data = null, string $message = 'OK'): void {
        self::json(['success' => true, 'message' => $message, 'data' => $data]);
    }

    public static function error(string $message, int $status = 400, $errors = null): void {
        $res = ['success' => false, 'message' => $message];
        if ($errors !== null) $res['errors'] = $errors;
        self::json($res, $status);
    }

    public static function paginated(array $items, int $total, int $page, int $perPage): void {
        self::success([
            'items'    => $items,
            'total'    => $total,
            'page'     => $page,
            'per_page' => $perPage,
            'pages'    => ceil($total / $perPage),
        ]);
    }
}
