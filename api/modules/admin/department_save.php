<?php
// api/modules/admin/department_save.php
Auth::requireRole(['admin']);
$data = Validator::all();
$name = Validator::get('name');
$code = Validator::get('code');

if (!$name || !$code) {
    Response::error('请输入科室名称和编码');
}

$pdo = DB::getPDO();
$id = isset($data['id']) ? $data['id'] : null;

// 风险评估量表 (占位配置: 后续在护理端实现评估流程, 护士评估→医生确认后生效)
$allowedAssessments = ['caprini', 'padua', 'cha2ds2', 'hasbled', 'none'];
$riskAssessment = Validator::get('risk_assessment', 'caprini');
if (!in_array($riskAssessment, $allowedAssessments)) $riskAssessment = 'caprini';

if ($id) {
    $stmt = $pdo->prepare("UPDATE departments SET code=?, name=?, description=?, risk_assessment=?, is_active=? WHERE id=?");
    $stmt->execute([$code, $name, $data['description'] ?? '', $riskAssessment, $data['is_active'] ?? 1, $id]);
} else {
    $stmt = $pdo->prepare("INSERT INTO departments (code, name, description, risk_assessment, is_active, created_at) VALUES (?,?,?,?,?,?)");
    $stmt->execute([$code, $name, $data['description'] ?? '', $riskAssessment, $data['is_active'] ?? 1, DateHelper::now()]);
    $id = $pdo->lastInsertId();
}
Response::success(['id' => $id]);
