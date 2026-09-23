<?php
// api/core/response.php - 统一 JSON 响应
class Response {
    public static function json($data, $code = 200) {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function success($data = null, $message = 'success') {
        self::json([
            'status' => 'success',
            'message' => $message,
            'data' => $data
        ]);
    }

    public static function error($message, $code = 400) {
        self::json([
            'status' => 'error',
            'message' => $message,
            'data' => null
        ], $code);
    }

    public static function paginated($items, $total, $page, $perPage) {
        $totalPages = $perPage > 0 ? (int)ceil($total / $perPage) : 1;
        if ($totalPages < 1) $totalPages = 1;
        self::json([
            'status' => 'success',
            'message' => '',
            'data' => $items,
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => $totalPages
            ]
        ]);
    }
}
