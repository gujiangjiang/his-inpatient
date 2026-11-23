<?php
// api/modules/nurse/order/verify_batch.php - 护士一键核对: 批量核查该患者的全部待核查医嘱
// 仅处理 status=pending 的根医嘱; 子医嘱级联; 并发变更的行跳过
Auth::requireRole(['admin', 'nurse']);
$patientId = (int)Validator::get('patient_id', 0);
if (!$patientId) Response::error('请输入患者ID');
if (!can_access_patient(Auth::user(), $patientId)) Response::error('无权操作该患者', 403);

$pdo = DB::getPDO();
$orders = DB::select("SELECT id FROM orders
    WHERE patient_id = ? AND status = 'pending' AND parent_id IS NULL
    ORDER BY id", [$patientId]);
$ok = 0; $fail = 0;
$pdo->beginTransaction();
try {
    $now = DateHelper::now();
    $userId = Auth::user()['id'];
    foreach ($orders as $o) {
        $id = (int)$o['id'];
        $stmt = $pdo->prepare("UPDATE orders SET status = 'verified', verified_by = ?, verified_at = ?, updated_at = ?
            WHERE id = ? AND status = 'pending'");
        $stmt->execute([$userId, $now, $now, $id]);
        if ($stmt->rowCount() === 1) {
            $ok++;
            $pdo->prepare("UPDATE orders SET status = 'verified', verified_by = ?, verified_at = ?, updated_at = ?
                WHERE parent_id = ? AND status = 'pending'")
                ->execute([$userId, $now, $now, $id]);
        } else {
            $fail++;
        }
    }
    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollback();
    Response::error('核对失败：' . $e->getMessage());
}
try { if ($ok) notify_orders_sync($patientId); } catch (Exception $e) { }
Response::success(['ok' => $ok, 'fail' => $fail],
    '已核对 ' . $ok . ' 条待核查医嘱' . ($fail ? '，跳过并发变更 ' . $fail . ' 条' : ''));
