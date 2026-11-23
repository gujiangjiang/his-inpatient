<?php
// api/modules/emr/template_delete.php - 删除模板
// 规则: 医生仅可删除本科室模板; 管理员可删任意; 全院通用(内置)模板不允许医生删除
// 删除前解除文书引用 (文书自带守卫与内容, 不依赖模板)
$roles = ['admin', 'doctor'];
Auth::requireRole($roles);
$id = Validator::get('id');
if (!$id) Response::error('请输入模板ID');

$user = Auth::user();
$tpl = DB::selectOne("SELECT * FROM emr_templates WHERE id = ?", [$id]);
if (!$tpl) Response::error('模板不存在', 404);
if ($user['role'] !== 'admin') {
    if ($tpl['department_id'] === null) Response::error('全院通用模板不允许删除', 403);
    if ((int)$tpl['department_id'] !== (int)($user['department_id'] ?? 0)) Response::error('仅可删除本科室模板', 403);
}

$pdo = DB::getPDO();
$pdo->beginTransaction();
try {
    $pdo->prepare("UPDATE emr_documents SET template_id = NULL WHERE template_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM emr_templates WHERE id = ?")->execute([$id]);
    $pdo->commit();
    Response::success();
} catch (Exception $e) {
    $pdo->rollback();
    Response::error('删除失败：' . $e->getMessage());
}
