<?php
// api/modules/emr/document_save.php
// 服务端强制校验: signed 状态文档禁止写入 (防前端越权绕过签名锁)
Auth::requireRole(['admin', 'doctor']);
$data = Validator::all();

$patientId = Validator::get('patient_id');
$categoryId = Validator::get('category_id');
$templateId = Validator::get('template_id');
$title = Validator::get('title');
$sections = Validator::get('sections'); // JSON 或数组
$docMeta = Validator::get('doc_meta');   // JSON 或数组

if (!$patientId) Response::error('缺少患者ID');

$pdo = DB::getPDO();
$userId = Auth::user()['id'];
$patient = DB::selectOne("SELECT * FROM patients WHERE id = ?", [$patientId]);
if (!$patient) Response::error('患者不存在');

$id = isset($data['id']) && $data['id'] ? $data['id'] : null;

// 序列化 JSON 字段
$sectionsJson = is_string($sections) ? $sections : json_encode($sections, JSON_UNESCAPED_UNICODE);
$metaJson = is_string($docMeta) ? $docMeta : json_encode($docMeta, JSON_UNESCAPED_UNICODE);
$contentHtml = Validator::get('content_html', '');

$pdo->beginTransaction();
try {
    if ($id) {
        // 更新: 已签名文档禁止写入
        $existing = DB::selectOne("SELECT * FROM emr_documents WHERE id = ?", [$id]);
        if (!$existing) Response::error('文书不存在');
        if ($existing['status'] === 'signed') {
            Response::error('文书已签名锁定, 禁止修改', 403);
        }
        $stmt = $pdo->prepare("UPDATE emr_documents SET title=?, category_id=?, template_id=?, doc_meta=?, sections=?, content_html=?, updated_at=? WHERE id=?");
        $stmt->execute([$title, $categoryId, $templateId, $metaJson, $sectionsJson, $contentHtml, DateHelper::now(), $id]);
    } else {
        // 新建
        $stmt = $pdo->prepare("INSERT INTO emr_documents (patient_id, admission_no, category_id, template_id, title, doc_meta, sections, content_html, status, created_by, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?, 'draft', ?, ?, ?)");
        $stmt->execute([$patientId, $patient['admission_no'], $categoryId, $templateId, $title, $metaJson, $sectionsJson, $contentHtml, $userId, DateHelper::now(), DateHelper::now()]);
        $id = $pdo->lastInsertId();
    }
    $pdo->commit();
    Response::success(['id' => $id]);
} catch (Exception $e) {
    $pdo->rollback();
    Response::error('保存失败: ' . $e->getMessage());
}