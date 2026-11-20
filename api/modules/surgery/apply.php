<?php
// api/modules/surgery/apply.php - 提交手术申请
// 规则: 生成一条临时医嘱 (在{麻醉}下行{名称}（明晨8:00/立即）; 关联 surgeries.order_id);
//       择期手术 → 自动将该患者所有长期医嘱停至次日 08:00 (待护士确认);
//       急诊/日间 → 不停医嘱, 仅通知病区护士; 注: 急诊/日间同样生成临时医嘱供护士知晓
Auth::requireRole(['admin', 'doctor']);
$data = Validator::all();
$patientId = (int)Validator::get('patient_id', 0);
$category = (string)Validator::get('category', 'elective');
if (!in_array($category, ['elective', 'emergency', 'day'], true)) Response::error('手术类别不正确');
$surgeryName = trim((string)Validator::get('surgery_name', ''));
$anesthesia = trim((string)Validator::get('anesthesia_type', ''));
$position = trim((string)Validator::get('patient_position', ''));
$assessPlace = trim((string)Validator::get('assess_place', ''));
$side = trim((string)Validator::get('side', ''));
$surgeonId = (int)Validator::get('surgeon_id', 0);
$assist1 = (int)Validator::get('assist1_id', 0);
$assist2 = (int)Validator::get('assist2_id', 0);
$notes = trim((string)Validator::get('notes', ''));

if (!$patientId || $surgeryName === '') Response::error('请填写手术名称');
$patient = DB::selectOne("SELECT * FROM patients WHERE id = ?", [$patientId]);
if (!$patient) Response::error('患者不存在', 404);
if (!can_access_patient(Auth::user(), $patientId)) Response::error('权限不足', 403);
if (!is_patient_admitted($patientId)) Response::error('患者尚未入科（未指派主管医师），不能申请手术');
if (!$surgeonId) $surgeonId = (int)Auth::user()['id'];

$user = Auth::user();
$pdo = DB::getPDO();
$now = DateHelper::now();

/* 计划时间: 择期/日间 → 次日 08:00; 急诊 → 当前 */
$plannedAt = $category === 'emergency'
    ? date('Y-m-d H:i:s', strtotime(TimeHelper::nowLocal()))
    : date('Y-m-d 08:00:00', strtotime(TimeHelper::nowLocal() . ' +1 day'));
$timeText = $category === 'emergency' ? '立即' : '明晨8:00';
$orderContent = ($anesthesia !== '' ? '在' . $anesthesia . '下' : '') . '行' . $surgeryName . '（' . $timeText . '）';

$pdo->beginTransaction();
try {
    /* 1) 手术申请 */
    $stmt = $pdo->prepare("INSERT INTO surgeries (patient_id, admission_no, surgery_name, surgeon, anesthesia_type, surgery_date,
            category, patient_position, assess_place, side, assist1_id, assist2_id, status, planned_at, requested_at, requested_by, notes, created_by, created_at)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?, 'requested', ?,?,?,?,?,?)");
    $stmt->execute([
        $patientId, $patient['admission_no'], $surgeryName, $surgeonId, $anesthesia, $plannedAt,
        $category, $position, $assessPlace, $side, $assist1 ?: null, $assist2 ?: null,
        $plannedAt, $now, $user['id'], $notes, $user['id'], $now,
    ]);
    $surgeryId = (int)$pdo->lastInsertId();

    /* 2) 临时医嘱 (待护士核对; 自动生成标记 → 行内不可编辑, 作废需从手术申请取消) */
    $orderNo = doc_no('O');
    $pdo->prepare("INSERT INTO orders (patient_id, admission_no, parent_id, is_group_main, order_type, order_period, order_no,
            content, category, status, is_auto_generated, start_date, created_by, created_at, updated_at)
        VALUES (?,?,NULL,0,'general','temp',?,?, 'surgery', 'pending', 1, ?,?,?,?)")
        ->execute([$patientId, $patient['admission_no'], $orderNo, $orderContent, $plannedAt, $user['id'], $now, $now]);
    $orderId = (int)$pdo->lastInsertId();
    DB::execute("UPDATE surgeries SET order_id = ? WHERE id = ?", [$orderId, $surgeryId]);

    /* 3) 择期: 长期医嘱自动停至次日 08:00 (待护士确认) */
    $stopped = 0;
    if ($category === 'elective') {
        /* stop_date 为本地墙钟业务时间; start_date 亦为本地墙钟 — 用本地当前时间比较 (与 order_stop 一致) */
        $stopDate = date('Y-m-d 08:00:00', strtotime(TimeHelper::nowLocal() . ' +1 day'));
        $stop = $pdo->prepare("UPDATE orders SET status = 'stop_pending', stop_date = ?, stopped_by = ?, updated_at = ?
                               WHERE patient_id = ? AND order_period = 'long' AND status IN ('pending','verified','executing')
                                 AND (start_date IS NULL OR start_date = '' OR start_date <= ?)");
        $stop->execute([$stopDate, $user['id'], $now, $patientId, TimeHelper::nowLocal()]);
        $stopped = $stop->rowCount();
    }
    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollback();
    Response::error('手术申请失败：' . $e->getMessage());
}

/* 4) 通知病区护士 (急诊/日间仅通知) */
try {
    $catLabel = ['elective' => '择期', 'emergency' => '急诊', 'day' => '日间'][$category];
    notify_send([
        'roles' => ['nurse'], 'ward_id' => $patient['ward_id'] ? (int)$patient['ward_id'] : null,
        'type' => 'surgery',
        'title' => $catLabel . '手术申请：' . $patient['name'] . ' · ' . $orderContent,
        'body' => '申请医师：' . $user['name'] . ($category === 'elective'
            ? (' · 已自动将长期医嘱停至明晨8:00（' . $stopped . ' 条，待确认）') : ' · 请注意术前准备'),
        'link' => '#nurse/station', 'ref_type' => 'surgery', 'ref_id' => $surgeryId,
    ]);
} catch (Exception $e) { /* 通知失败不影响申请 */ }

Response::success(['id' => $surgeryId, 'order_id' => $orderId, 'content' => $orderContent, 'stopped_long' => ($stopped ?? 0)]);
