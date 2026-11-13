<?php
// api/modules/pharmacy/drug_search.php - 开嘱药品模糊检索
Auth::requireRole(['admin', 'doctor', 'pharmacist']);
$kw = trim((string)Validator::get('kw', ''));
$limit = (int)Validator::get('limit', 50);
if ($limit < 1 || $limit > 200) $limit = 50;

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

// 有库存优先, 再按名称
$sql .= " ORDER BY CASE WHEN stock_quantity > 0 THEN 0 ELSE 1 END, name ASC LIMIT {$limit}";

$stmt = DB::getPDO()->prepare($sql);
$stmt->execute($params);
Response::success($stmt->fetchAll(PDO::FETCH_ASSOC));
