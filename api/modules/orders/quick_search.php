<?php
// api/modules/orders/quick_search.php - 快速开嘱统一检索 (药品 + 非药品目录)
Auth::requireRole(['admin', 'doctor']);
$kw = trim((string)Validator::get('kw', ''));
$type = Validator::get('type', ''); // '' 全部 | medication | nonmedication
$limit = 30;

$items = [];

if ($type !== 'nonmedication') {
    $sql = "SELECT id, code, name, specification, unit, price, stock_quantity, dosage_form,
                   manufacturer, pharmacy, drug_class, pinyin_code, default_freq, default_route,
                   default_dosage, is_antibiotic
            FROM medications WHERE 1=1";
    $params = [];
    if ($kw !== '') {
        $sql .= " AND (name LIKE ? OR pinyin_code LIKE ? OR code LIKE ?)";
        $like = '%' . $kw . '%';
        $params = [$like, $like . '%', $like];
    }
    $sql .= " ORDER BY CASE WHEN stock_quantity > 0 THEN 0 ELSE 1 END, name ASC LIMIT {$limit}";
    $stmt = DB::getPDO()->prepare($sql);
    $stmt->execute($params);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $row['result_type'] = 'medication';
        $items[] = $row;
    }
}

if ($type !== 'medication') {
    $sql = "SELECT * FROM order_catalog WHERE is_active = 1";
    $params = [];
    if ($kw !== '') {
        $sql .= " AND content LIKE ?";
        $params[] = '%' . $kw . '%';
    }
    $sql .= " ORDER BY sort_no ASC, id ASC LIMIT {$limit}";
    $stmt = DB::getPDO()->prepare($sql);
    $stmt->execute($params);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $row['result_type'] = 'nonmedication';
        $row['name'] = $row['content'];
        $items[] = $row;
    }
}

Response::success(['items' => $items]);
