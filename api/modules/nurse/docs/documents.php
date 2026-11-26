<?php
// api/modules/nurse/docs/documents.php - 常规护理文书 (入院评估/护理计划/健康宣教/转科记录等)
// 动作: list 查询 (admin/nurse/doctor 只读) | save 新增/更新 (admin/nurse) | delete 删除 (admin/nurse)
$action = Validator::get('action', 'list');
$user = Auth::user();

if ($action === 'list') {
    Auth::requireRole(['admin', 'nurse', 'doctor']);
    $patientId = Validator::get('patient_id');
    if (!$patientId) Response::error('请输入患者ID');
    if (!can_access_patient($user, (int)$patientId)) Response::error('无权访问该患者', 403);
    $rows = DB::select(
        "SELECT nd.*, u.name AS recorder_name
         FROM nursing_docs nd
         LEFT JOIN users u ON nd.recorded_by = u.id
         WHERE nd.patient_id = ?
         ORDER BY nd.created_at DESC, nd.id DESC", [$patientId]);
    Response::success($rows);
}

/* 新建文书预填数据: 主诉/现病史/既往史取同患者 EMR 入院记录 (fx 槽位), 无则回退患者入院诊断;
   生命体征/身体状况取首次护理记录 (record_time 最早)。返回纯文本, 前端回填模板控件 (可修改)。 */
if ($action === 'prefill') {
    Auth::requireRole(['admin', 'nurse', 'doctor']);
    $patientId = (int)Validator::get('patient_id', 0);
    if (!$patientId) Response::error('请输入患者ID');
    if (!can_access_patient($user, $patientId)) Response::error('无权访问该患者', 403);
    $out = ['chief_complaint' => '', 'present_illness' => '', 'past_history' => '', 'vital' => []];

    /* EMR 入院记录: 取最早的入院类文书, 解析 doc_json 的 fx 槽位文本 (段落内 fx 标签后跟 txt) */
    $emr = DB::selectOne(
        "SELECT d.doc_json FROM emr_documents d
         LEFT JOIN emr_categories c ON d.category_id = c.id
         WHERE d.patient_id = ? AND d.doc_json IS NOT NULL AND d.doc_json != ''
           AND (d.title LIKE '%入院记录%' OR c.name LIKE '%入院记录%')
         ORDER BY d.created_at ASC, d.id ASC LIMIT 1", [$patientId]);
    $parseFx = function ($doc) {
        $map = [];
        $lastKey = null;
        $walk = function ($nodes) use (&$walk, &$map, &$lastKey) {
            if (!is_array($nodes)) return;
            foreach ($nodes as $n) {
                if (!is_array($n)) continue;
                if (isset($n['runs']) && is_array($n['runs'])) {
                    foreach ($n['runs'] as $r) {
                        if (isset($r['t']) && $r['t'] === 'fx') { $lastKey = isset($r['key']) ? $r['key'] : null; $map[$lastKey] = $map[$lastKey] ?? ''; }
                        elseif (isset($r['t']) && $r['t'] === 'txt' && $lastKey) $map[$lastKey] .= $r['text'];
                    }
                } else { $walk($n); }
            }
        };
        $walk($doc['body'] ?? []);
        return $map;
    };
    if ($emr && $emr['doc_json']) {
        try {
            $fx = $parseFx(json_decode($emr['doc_json'], true));
            foreach (['chief_complaint', 'present_illness', 'past_history'] as $f) {
                if (isset($fx[$f])) $out[$f] = trim(preg_replace('/\s+/u', ' ', $fx[$f]));
            }
        } catch (\Throwable $e) { /* JSON 损坏时忽略, 走回退 */ }
    }
    /* 回退: 无 EMR 主诉时用患者入院诊断 */
    if ($out['chief_complaint'] === '') {
        $p = DB::selectOne("SELECT admission_diagnosis FROM patients WHERE id = ?", [$patientId]);
        if ($p && trim((string)$p['admission_diagnosis'])) $out['chief_complaint'] = trim((string)$p['admission_diagnosis']);
    }
    /* 首次护理记录 (最早一条) */
    $note = DB::selectOne(
        "SELECT temperature, pulse, respiratory_rate, bp_systolic, bp_diastolic, oxygen_saturation,
                consciousness, pupil, height, weight, pain_score
         FROM nursing_notes WHERE patient_id = ?
         ORDER BY record_time ASC, id ASC LIMIT 1", [$patientId]);
    if ($note) {
        foreach (['temperature', 'pulse', 'respiratory_rate', 'bp_systolic', 'bp_diastolic',
                  'oxygen_saturation', 'consciousness', 'pupil', 'height', 'weight', 'pain_score'] as $f) {
            if ($note[$f] !== null && $note[$f] !== '') $out['vital'][$f] = $note[$f];
        }
    }
    Response::success($out);
}

/* 批量打印留痕: print_count 累加 + printed_at, 返回 id → 最新次数映射 (弹窗即时刷新显示) */
if ($action === 'mark_printed') {
    Auth::requireRole(['admin', 'nurse']);
    $ids = Validator::get('ids');
    if (!$ids || !is_array($ids)) Response::error('请选择文书');
    $ids = array_values(array_unique(array_map('intval', $ids)));
    if (!$ids) Response::error('请选择文书');
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $rows = DB::select("SELECT id, patient_id FROM nursing_docs WHERE id IN ($ph)", $ids);
    foreach ($rows as $row) {
        if (!can_access_patient($user, (int)$row['patient_id'])) Response::error('无权操作该患者', 403);
    }
    DB::execute("UPDATE nursing_docs SET print_count = print_count + 1, printed_at = ? WHERE id IN ($ph)",
        array_merge([DateHelper::now()], $ids));
    $counts = [];
    foreach (DB::select("SELECT id, print_count FROM nursing_docs WHERE id IN ($ph)", $ids) as $c) {
        $counts[(int)$c['id']] = (int)$c['print_count'];
    }
    Response::success(['counts' => $counts], '打印已记录');
}

if ($action === 'save' || $action === 'delete') {
    Auth::requireRole(['admin', 'nurse']);
}

if ($action === 'save') {
    $patientId = (int)Validator::get('patient_id', 0);
    if (!$patientId) Response::error('请选择患者');
    if (!can_access_patient(Auth::user(), $patientId)) Response::error('无权操作该患者', 403);
    $docType = trim((string)Validator::get('doc_type', ''));
    $title = trim((string)Validator::get('title', ''));
    $content = trim((string)Validator::get('content', ''));
    if ($docType === '') Response::error('请选择文书类型');
    if ($title === '') Response::error('请输入文书标题');
    if ($content === '') Response::error('请输入文书内容');

    $patient = DB::selectOne("SELECT admission_no FROM patients WHERE id = ?", [$patientId]);
    if (!$patient) Response::error('患者不存在');

    $now = DateHelper::now();
    $userId = Auth::user()['id'];
    $id = (int)Validator::get('id', 0);
    $pdo = DB::getPDO();

    if ($id) {
        $exist = DB::selectOne("SELECT patient_id FROM nursing_docs WHERE id = ?", [$id]);
        if (!$exist) Response::error('文书不存在');
        if (!can_access_patient(Auth::user(), (int)$exist['patient_id'])) Response::error('无权操作该文书', 403);
        DB::execute("UPDATE nursing_docs SET doc_type = ?, title = ?, content = ?, recorded_by = ?, updated_at = ?
                     WHERE id = ?",
            [$docType, $title, $content, $userId, $now, $id]);
        Response::success(['id' => $id], '护理文书已更新');
    }

    $stmt = $pdo->prepare(
        "INSERT INTO nursing_docs (patient_id, admission_no, doc_type, title, content, recorded_by, created_at, updated_at)
         VALUES (?,?,?,?,?,?,?,?)");
    $stmt->execute([$patientId, $patient['admission_no'], $docType, $title, $content, $userId, $now, $now]);
    Response::success(['id' => (int)$pdo->lastInsertId()], '护理文书已保存');
}

if ($action === 'delete') {
    $id = (int)Validator::get('id', 0);
    if (!$id) Response::error('参数错误');
    $row = DB::selectOne("SELECT patient_id FROM nursing_docs WHERE id = ?", [$id]);
    if (!$row) Response::error('文书不存在');
    if (!can_access_patient(Auth::user(), (int)$row['patient_id'])) Response::error('无权操作该文书', 403);
    DB::execute("DELETE FROM nursing_docs WHERE id = ?", [$id]);
    Response::success([], '已删除');
}