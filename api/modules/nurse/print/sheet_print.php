<?php
// api/modules/nurse/print/sheet_print.php - 执行单打印留痕 (批量标记已打印)
Auth::requireRole(['admin', 'nurse']);
$patientId = (int)Validator::get('patient_id', 0);
$ids = Validator::get('ids');
if (!$patientId) Response::error('请输入患者ID');
if (!is_array($ids) || !$ids) Response::error('请选择要打印的医嘱');
if (!can_access_patient(Auth::user(), $patientId)) Response::error('无权操作该患者', 403);

$pdo = DB::getPDO();
$userId = (int)Auth::user()['id'];
$now = DateHelper::now();

$ok = 0; $fail = [];
$pdo->beginTransaction();
try {
    foreach (array_unique(array_map('intval', $ids)) as $oid) {
        // 仅该患者已核对/执行/完成的药品根医嘱可留痕
        $order = DB::selectOne(
            "SELECT id FROM orders
             WHERE id = ? AND patient_id = ? AND category = 'medication'
               AND status IN ('verified', 'executing', 'completed')",
            [$oid, $patientId]
        );
        if (!$order) { $fail[] = '医嘱#' . $oid; continue; }
        $stmt = $pdo->prepare("INSERT INTO order_exec_sheet_prints (order_id, patient_id, printed_by, printed_at)
                               VALUES (?, ?, ?, ?)");
        $stmt->execute([$oid, $patientId, $userId, $now]);
        $ok++;
    }
    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollback();
    Response::error('打印留痕失败：' . $e->getMessage());
}
Response::success(['ok' => $ok, 'fail' => $fail],
    '执行单打印已记录 ' . $ok . ' 条' . ($fail ? '，跳过无效医嘱 ' . count($fail) . ' 条' : ''));