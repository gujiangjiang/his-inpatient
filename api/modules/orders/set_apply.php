<?php
// api/modules/orders/set_apply.php - 套用医嘱组套 (生成草稿医嘱)
Auth::requireRole(['admin', 'doctor']);
$data = Validator::all();
$setId = Validator::get('set_id');
$patientId = Validator::get('patient_id');

if (!$setId || !$patientId) Response::error('缺少组套或患者');

$set = DB::selectOne("SELECT * FROM order_sets WHERE id = ? AND is_active = 1", [$setId]);
if (!$set) Response::error('组套不存在');
$patient = DB::selectOne("SELECT * FROM patients WHERE id = ?", [$patientId]);
if (!$patient) Response::error('患者不存在');
if (!is_patient_admitted($patientId)) Response::error('患者尚未入科（未指派主管医师），不能开医嘱');

$items = DB::select("SELECT * FROM order_set_items WHERE set_id = ? ORDER BY sort_no ASC, id ASC", [$setId]);
if (!$items) Response::error('组套内容为空');

$userId = Auth::user()['id'];
$now = DateHelper::now();
$status = (int)Validator::get('submit', 0) ? 'pending' : 'draft';
$isGroup = count($items) > 1 && $set['category'] === 'medication'; // 仅药品成组, 非药品逐条独立

$pdo = DB::getPDO();
$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare("INSERT INTO orders (patient_id, admission_no, parent_id, is_group_main, order_type, order_period,
                order_no, content, category, status, drug_id, dosage, dosage_unit, usage_name, usage_rate,
                freq_code, freq_detail, billing_flags, group_seq, sort_no, start_date, created_by, created_at, updated_at)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
    $mainId = null;
    $ids = [];
    foreach ($items as $i => $item) {
        $orderNo = 'O' . date('YmdHis') . str_pad((string)mt_rand(0, 999), 3, '0', STR_PAD_LEFT);
        $itemPeriod = $item['order_period'] ?: 'long';
        $freqCode = $item['freq_code'];
        $freqDetail = $item['freq_detail'];
        // 临时医嘱无频次/执行时间
        if ($itemPeriod === 'temp') { $freqCode = ''; $freqDetail = ''; }
        $stmt->execute([
            $patientId, $patient['admission_no'],
            ($isGroup && $i > 0) ? $mainId : null,
            ($isGroup && $i === 0) ? 1 : 0,
            ($item['category'] === 'medication') ? 'medication' : 'general',
            $itemPeriod,
            $orderNo, $item['content'], $item['category'], $status,
            $item['drug_id'], $item['dosage'], $item['dosage_unit'],
            $item['usage_name'], $item['usage_rate'],
            $freqCode, $freqDetail, '',
            $i, 0, $now, $userId, $now, $now
        ]);
        $id = $pdo->lastInsertId();
        $ids[] = $id;
        if ($i === 0) $mainId = $id;
    }
    $pdo->commit();
    Response::success(['id' => $mainId, 'ids' => $ids, 'count' => count($ids)]);
} catch (Exception $e) {
    $pdo->rollback();
    Response::error('套用组套失败：' . $e->getMessage());
}
