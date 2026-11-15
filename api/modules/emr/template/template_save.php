<?php
// api/modules/emr/template_save.php
$roles = ['admin', 'doctor'];
Auth::requireRole($roles);
$data = Validator::all();

$name = Validator::get('name');
$recordType = Validator::get('record_type');
$contentDelta = Validator::get('content_delta');
$contentHtml = Validator::get('content_html');
$schemaJson = Validator::get('schema_json');   // 字处理编辑器模板 schema (可选)
$categoryId = Validator::get('category_id');
$departmentId = Validator::get('department_id'); // 科室归属; null=自动(新建取本人科室, 更新保持原值), 0=全院通用

if (!$name || !$recordType || !$contentDelta) {
    Response::error('缺少必要参数');
}

// schema_json 需为合法 JSON
if ($schemaJson !== null && $schemaJson !== '') {
    $schemaArr = is_string($schemaJson) ? json_decode($schemaJson, true) : $schemaJson;
    if (!is_array($schemaArr)) Response::error('schema_json 格式错误');
    $schemaJson = is_string($schemaJson) ? $schemaJson : json_encode($schemaArr, JSON_UNESCAPED_UNICODE);
}

/* 自动计算项校验: 公式引用的字段代码必须在本模板中存在 (否则该模板新建文书无法保存) */
if ($schemaJson !== null && $schemaJson !== '') {
    $schemaCheck = is_string($schemaJson) ? json_decode($schemaJson, true) : $schemaJson;
    if (is_array($schemaCheck) && is_array($schemaCheck['body'] ?? null)) {
        $maintMissing = emr_calc_missing(['body' => $schemaCheck['body']]);
        if ($maintMissing) {
            $msgs = [];
            foreach ($maintMissing as $mm) $msgs[] = '「' . $mm[0] . '」缺少字段: ' . implode('、', $mm[1]);
            Response::error('自动计算项校验失败: ' . implode('; ', $msgs));
        }
    }
}

$pdo = DB::getPDO();
$userId = Auth::user()['id'];
$id = isset($data['id']) && $data['id'] ? $data['id'] : null;

if ($id) {
    $existing = DB::selectOne("SELECT department_id, created_by FROM emr_templates WHERE id = ?", [$id]);
    $cur = Auth::user();
    if ($cur['role'] !== 'admin' && $existing && (int)$existing['created_by'] !== (int)$cur['id']) Response::error('仅创建人可编辑', 403);
    $departmentId = resolve_department_id($departmentId, $existing ? $existing['department_id'] : null);
    $stmt = $pdo->prepare("UPDATE emr_templates SET name=?, content_delta=?, content_html=?, schema_json=?, category_id=?, department_id=? WHERE id=?");
    $stmt->execute([$name, $contentDelta, $contentHtml, $schemaJson, $categoryId, $departmentId, $id]);
} else {
    $departmentId = resolve_department_id($departmentId, null);
    $stmt = $pdo->prepare("INSERT INTO emr_templates (name, record_type, content_delta, content_html, schema_json, category_id, department_id, usage_count, created_by, created_at, pub_status) VALUES (?,?,?,?,?,?,?,0,?,?,'personal')");
    $stmt->execute([$name, $recordType, $contentDelta, $contentHtml, $schemaJson, $categoryId, $departmentId, $userId, DateHelper::now()]);
    $id = $pdo->lastInsertId();
}
Response::success(['id' => $id]);
