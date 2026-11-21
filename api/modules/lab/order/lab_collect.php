<?php
// api/modules/lab/lab_collect.php
Auth::requireRole(['admin', 'nurse']);
$data = Validator::all();
$id = Validator::get('id');
$action = Validator::get('action', 'collect');

if (!$id) Response::error('请输入检验单ID');

$pdo = DB::getPDO();
$report = DB::selectOne("SELECT * FROM lab_reports WHERE id = ?", [$id]);
if (!$report) Response::error('检验单不存在');
/* 归属校验: 护士仅可采集本病区患者的检验单 */
if (!can_access_patient(Auth::user(), (int)$report['patient_id'])) Response::error('无权操作该检验单', 403);

if ($report['status'] === 'collected' || $report['status'] === 'reported') {
    Response::error('状态不允许操作');
}

DB::execute("UPDATE lab_reports SET status = 'collected', updated_at = ? WHERE id = ?", [DateHelper::now(), $id]);
Response::success();
