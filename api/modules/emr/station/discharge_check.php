<?php
// api/modules/emr/discharge_check.php - 出院前校验
// 拦截项: 未核对医嘱(草稿/待核/停止待核/作废待核) / 未发放处方(药品医嘱已核对但无发药记录=未扣费)
// 提示项(不拦截): 未出结果的检验/检查 —— 住院开单即已扣费, 不要求出结果
Auth::requireRole(['admin', 'doctor']);
$patientId = (int)Validator::get('patient_id', 0);
if (!$patientId) Response::error('缺少 patient_id');
$patient = DB::selectOne("SELECT * FROM patients WHERE id = ?", [$patientId]);
if (!$patient) Response::error('患者不存在', 404);
if (!can_access_patient(Auth::user(), $patientId)) Response::error('无权操作该患者', 403);

$orders = DB::select(
    "SELECT id, order_no, content, status, order_period FROM orders
     WHERE patient_id = ? AND status IN ('draft','pending','stop_pending','invalid_pending')
     ORDER BY created_at ASC",
    [$patientId]
);
$labs = DB::select(
    "SELECT report_no, exam_name, status FROM lab_reports WHERE patient_id = ? AND status IN ('pending','collected') ORDER BY id ASC",
    [$patientId]
);
$exams = DB::select(
    "SELECT report_no, exam_type, status FROM exam_reports WHERE patient_id = ? AND status = 'pending' ORDER BY id ASC",
    [$patientId]
);
$meds = DB::select(
    "SELECT o.id, o.order_no, o.content FROM orders o
     WHERE o.patient_id = ? AND o.category = 'medication' AND o.status IN ('verified','executing')
       AND NOT EXISTS (SELECT 1 FROM dispensing_records d WHERE d.order_id = o.id)
     ORDER BY o.created_at ASC",
    [$patientId]
);
/* 检验检查: 开单即已扣费, 未出结果不拦截出院, 仅作提示 */
$info = [];
if (count($labs)) $info[] = '存在未出结果的检验 ' . count($labs) . ' 项（开单即已扣费，不影响出院）';
if (count($exams)) $info[] = '存在未出结果的检查 ' . count($exams) . ' 项（开单即已扣费，不影响出院）';
$ok = !count($orders) && !count($meds);
Response::success([
    'ok' => $ok,
    'orders' => $orders,
    'meds' => $meds,
    'info' => $info,
    'labs' => $labs,
    'exams' => $exams,
]);
