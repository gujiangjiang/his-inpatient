<?php
// api/modules/emr/document_save.php
// 保存文书 (字处理编辑器统一模式):
//   doc_json 单一数据源; 服务端强制校验固定元素 (fx/slot 键集合) 防前端越权篡改
//   守卫优先级: 文书 doc_meta.guard (建档时固化, 模板后续变更不影响老文书) > 模板 schema
//   signed 状态文档禁止写入 (防前端越权绕过签名锁)
Auth::requireRole(['admin', 'doctor']);
$data = Validator::all();

$patientId = Validator::get('patient_id');
$categoryId = Validator::get('category_id');
$templateId = Validator::get('template_id');
$title = Validator::get('title');
$sections = Validator::get('sections'); // 旧结构备份 (可选)
$docMeta = Validator::get('doc_meta');   // JSON 或数组
$docJson = Validator::get('doc_json');   // JSON 或数组 (字处理编辑器)

if (!$patientId) Response::error('缺少患者ID');

$pdo = DB::getPDO();
$userId = Auth::user()['id'];
$patient = DB::selectOne("SELECT * FROM patients WHERE id = ?", [$patientId]);
if (!$patient) Response::error('患者不存在');

$id = isset($data['id']) && $data['id'] ? $data['id'] : null;

// 序列化 JSON 字段
$sectionsJson = $sections !== null && $sections !== '' ? (is_string($sections) ? $sections : json_encode($sections, JSON_UNESCAPED_UNICODE)) : null;
$metaJson = $docMeta !== null && $docMeta !== '' ? (is_string($docMeta) ? $docMeta : json_encode($docMeta, JSON_UNESCAPED_UNICODE)) : '{}';
$metaArr = json_decode($metaJson, true);
$metaArr = is_array($metaArr) ? $metaArr : [];
$docJsonStr = null;

if ($docJson !== null && $docJson !== '') {
    $docArr = is_string($docJson) ? json_decode($docJson, true) : $docJson;
    if (!is_array($docArr)) Response::error('doc_json 格式错误');

    // 守卫: 文书自带 doc_meta.guard 优先; 无则取模板 schema 并写回文书
    $guard = (isset($metaArr['guard']) && is_array($metaArr['guard'])) ? $metaArr['guard'] : null;
    if (!$guard) {
        $guard = templateGuard($templateId);
        if ($guard) $metaArr['guard'] = $guard;
    }
    validateDocJson($docArr, $guard);
    $docJsonStr = is_string($docJson) ? $docJson : json_encode($docArr, JSON_UNESCAPED_UNICODE);
    $metaJson = json_encode($metaArr, JSON_UNESCAPED_UNICODE);
}

$contentHtml = Validator::get('content_html', '');

// 数据库结构守卫 (正常由 SchemaGuard 自动迁移, 此处为兜底): 缺列时给出明确指引而非原始 SQL 报错
$mig = new Migration();
if (!$mig->hasColumn('emr_documents', 'doc_json')) {
    Response::error('数据库未升级: 请执行数据库迁移 (npm run migrate 或 php api/scripts/migrate.php)', 409);
}

$pdo->beginTransaction();
try {
    if ($id) {
        // 更新: 已签名文档禁止写入
        $existing = DB::selectOne("SELECT * FROM emr_documents WHERE id = ?", [$id]);
        if (!$existing) Response::error('文书不存在');
        if ($existing['status'] === 'signed') {
            Response::error('文书已签名锁定, 禁止修改', 403);
        }
        $stmt = $pdo->prepare("UPDATE emr_documents SET title=?, category_id=?, template_id=?, doc_meta=?, sections=?, content_html=?, doc_json=?, updated_at=? WHERE id=?");
        $stmt->execute([$title, $categoryId, $templateId, $metaJson, $sectionsJson, $contentHtml, $docJsonStr, DateHelper::now(), $id]);
    } else {
        // 新建
        $stmt = $pdo->prepare("INSERT INTO emr_documents (patient_id, admission_no, category_id, template_id, title, doc_meta, sections, content_html, doc_json, status, created_by, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?, 'draft', ?, ?, ?)");
        $stmt->execute([$patientId, $patient['admission_no'], $categoryId, $templateId, $title, $metaJson, $sectionsJson, $contentHtml, $docJsonStr, $userId, DateHelper::now(), DateHelper::now()]);
        $id = $pdo->lastInsertId();
    }

    // 落一版历史 (仅字处理编辑器文档, 保留最近 20 版)
    if ($docJsonStr !== null) {
        $stmt = $pdo->prepare("INSERT INTO emr_document_versions (doc_id, doc_json, content_html, action, created_by, created_at) VALUES (?,?,?, 'save', ?, ?)");
        $stmt->execute([$id, $docJsonStr, $contentHtml, $userId, DateHelper::now()]);
        $pdo->exec("DELETE FROM emr_document_versions WHERE doc_id = " . intval($id) . " AND id NOT IN (SELECT id FROM emr_document_versions WHERE doc_id = " . intval($id) . " ORDER BY id DESC LIMIT 20)");
    }

    $pdo->commit();
    Response::success(['id' => $id]);
} catch (Exception $e) {
    $pdo->rollback();
    Response::error('保存失败: ' . $e->getMessage());
}

/** 递归提取正文块的固定元素键: {fx:[...], slot:[...]} (排序去重) */
function emrExtractKeys($body) {
    $keys = ['fx' => [], 'slot' => []];
    $walk = function ($blocks) use (&$walk, &$keys) {
        foreach ($blocks as $b) {
            if (!is_array($b)) continue;
            if (($b['t'] ?? null) === 'p') {
                foreach (($b['runs'] ?? []) as $it) {
                    /* dyn (动态只读项: BMI/签名/诊断同步) 与 fx 同组守卫 */
                    if ((($it['t'] ?? null) === 'fx' || ($it['t'] ?? null) === 'dyn') && isset($it['key'])) $keys['fx'][] = $it['key'];
                    if (($it['t'] ?? null) === 'slot' && isset($it['key'])) $keys['slot'][] = $it['key'];
                }
            } elseif (($b['t'] ?? null) === 'tbl') {
                foreach (($b['rows'] ?? []) as $r) foreach (($r['cells'] ?? []) as $c) $walk($c['blocks'] ?? []);
            }
        }
    };
    $walk($body);
    $keys['fx'] = array_values(array_unique($keys['fx'])); sort($keys['fx']);
    $keys['slot'] = array_values(array_unique($keys['slot'])); sort($keys['slot']);
    return $keys;
}

/** 模板 schema (word 模式) 的固定元素键; 非 word 模板返回 null (不校验) */
function templateGuard($templateId) {
    if (!$templateId) return null;
    $tpl = DB::selectOne("SELECT schema_json FROM emr_templates WHERE id = ?", [$templateId]);
    if (!$tpl || empty($tpl['schema_json'])) return null;
    $schema = json_decode($tpl['schema_json'], true);
    if (!is_array($schema) || ($schema['mode'] ?? null) !== 'word') return null;
    return emrExtractKeys($schema['body'] ?? []);
}

/** 校验字处理文档: 结构完整性 + 固定元素键与守卫一致 (不可增删/篡改) */
function validateDocJson($doc, $guard) {
    $errors = [];
    if (!isset($doc['v']) || $doc['v'] !== 1) $errors[] = '文档版本 v 必须为 1';
    if (!is_array($doc['body'] ?? null)) $errors[] = '缺少正文 body 数组';
    if (!is_array($doc['header'] ?? null)) $errors[] = '缺少抬头 header';
    if (!is_array($doc['page'] ?? null)) $errors[] = '缺少排版 page 配置';
    if ($errors) Response::error('doc_json 校验失败: ' . implode('; ', $errors));

    if (is_array($guard)) {
        $now = emrExtractKeys($doc['body']);
        foreach (['fx', 'slot'] as $kind) {
            $want = $guard[$kind] ?? [];
            sort($want);
            if ($now[$kind] !== $want) {
                Response::error('固定元素被改动, 禁止保存 (不可增删/篡改固定标签或填写槽)');
            }
        }
    }
}