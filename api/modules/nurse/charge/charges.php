<?php
// api/modules/nurse/charge/charges.php - 患者费用流水 (护士费用核对/补录)
// 动作: list 查询全部费用 (按类型分组 + 合计) | add 补录费用 | delete 删除补录
Auth::requireRole(['admin', 'nurse']);
$user = Auth::user();
$action = Validator::get('action', 'list');

if ($action === 'list') {
    $patientId = (int)Validator::get('patient_id', 0);
    if (!$patientId) Response::error('请输入患者ID');
    if (!can_access_patient($user, $patientId)) Response::error('无权访问该患者', 403);
    $rows = DB::select(
        "SELECT pc.*, u.name AS recorder_name
         FROM patient_charges pc
         LEFT JOIN users u ON pc.created_by = u.id
         WHERE pc.patient_id = ?
         ORDER BY pc.id ASC", [$patientId]);
    // 按类型分组 + 合计
    $types = ['care' => '护理', 'medical' => '医疗', 'drug' => '药品', 'material' => '耗材', 'other' => '其他'];
    $groups = [];
    $total = 0;
    foreach ($rows as $r) {
        $t = $r['charge_type'] ?? 'other';
        if (!isset($groups[$t])) $groups[$t] = ['label' => $types[$t] ?? $t, 'items' => [], 'amount' => 0];
        $groups[$t]['items'][] = $r;
        $groups[$t]['amount'] += (float)$r['amount'];
        $total += (float)$r['amount'];
    }
    Response::success(['groups' => $groups, 'total' => round($total, 2), 'count' => count($rows)]);
}

if ($action === 'add') {
    $patientId = (int)Validator::get('patient_id', 0);
    if (!$patientId) Response::error('请选择患者');
    if (!can_access_patient($user, $patientId)) Response::error('无权操作该患者', 403);
    $chargeType = trim((string)Validator::get('charge_type', 'material'));
    $itemName = trim((string)Validator::get('item_name', ''));
    $qty = (float)Validator::get('qty', 1);
    $price = (float)Validator::get('price', 0);
    $remark = trim((string)Validator::get('remark', ''));
    if ($itemName === '') Response::error('请填写费用项目名称');
    if ($qty <= 0) Response::error('数量必须大于 0');
    if ($price < 0) Response::error('单价不能为负数');
    $patient = DB::selectOne("SELECT admission_no FROM patients WHERE id = ?", [$patientId]);
    if (!$patient) Response::error('患者不存在');
    $now = DateHelper::now();
    $amount = round($qty * $price, 2);
    $stmt = DB::getPDO()->prepare(
        "INSERT INTO patient_charges (patient_id, admission_no, charge_type, item_name, qty, price, amount, source, remark, created_by, created_at)
         VALUES (?,?,?,?,?,?,?, 'manual', ?, ?, ?)");
    $stmt->execute([$patientId, $patient['admission_no'], $chargeType, $itemName, $qty, $price, $amount, $remark, $user['id'], $now]);
    Response::success(['id' => (int)DB::getPDO()->lastInsertId()], '费用已补录');
}

if ($action === 'delete') {
    $id = (int)Validator::get('id', 0);
    if (!$id) Response::error('参数错误');
    $row = DB::selectOne("SELECT patient_id, source FROM patient_charges WHERE id = ?", [$id]);
    if (!$row) Response::error('费用记录不存在');
    if ($row['source'] !== 'manual') Response::error('仅可删除手工补录的费用');
    if (!can_access_patient($user, (int)$row['patient_id'])) Response::error('无权操作该记录', 403);
    DB::execute("DELETE FROM patient_charges WHERE id = ?", [$id]);
    Response::success([], '已删除');
}
