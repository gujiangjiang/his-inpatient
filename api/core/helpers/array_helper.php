<?php
// api/core/helpers/array_helper.php
class ArrayHelper {
    public static function get($array, $key, $default = null) {
        return isset($array[$key]) ? $array[$key] : $default;
    }

    public static function paginate($items, $total, $page, $perPage) {
        $totalPages = $perPage > 0 ? (int)ceil($total / $perPage) : 1;
        if ($totalPages < 1) $totalPages = 1;
        return [
            'items' => $items,
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => $totalPages
            ]
        ];
    }

    public static function sanitize($data, $fields) {
        $result = [];
        foreach ($fields as $field) {
            $result[$field] = isset($data[$field]) ? $data[$field] : null;
        }
        return $result;
    }
}
