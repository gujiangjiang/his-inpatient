<?php
// api/core/helpers/opd_order_push.php - 住院检验/检查单推送门诊（待发队列 + 重试）
// 说明: 住院开单即计费（HIS 收费口径），检验/检查申请提交后自动推送门诊一体化
// 系统登记出报告；载荷落 integration_order_pushes 队列（biz_type+biz_id 幂等），
// 推送失败自动累计重试次数，由 CLI（api/scripts/push_opd_orders.php）或接口管理页
// 「重推失败单据」补偿。推送失败不阻塞开单主流程。

/** 推送是否启用（门诊接口已启用且配置服务地址） */
function opd_order_push_enabled() {
    $s = opd_settings();
    return $s['opd_enabled'] === '1' && trim((string)$s['opd_base_url']) !== '';
}

/**
 * 组装并入库待推送载荷（已成功的同一单据不重复入队）
 * @param string $bizType  lab 检验 / exam 检查
 * @param int    $reportId lab_reports.id / exam_reports.id
 * @return bool 是否成功入队
 */
function opd_order_push_submit($bizType, $reportId) {
    $bizType = $bizType === 'exam' ? 'exam' : 'lab';
    $table = $bizType === 'exam' ? 'exam_reports' : 'lab_reports';
    $report = DB::selectOne("SELECT * FROM {$table} WHERE id = ?", [(int)$reportId]);
    if (!$report) return false;
    $payload = opd_order_push_payload($bizType, $report);
    if (!$payload) return false;

    $existing = DB::selectOne("SELECT * FROM integration_order_pushes WHERE biz_type = ? AND biz_id = ?", [$bizType, (int)$reportId]);
    if ($existing && $existing['status'] === 'success') return true;
    $now = DateHelper::now();
    $data = [
        'report_no' => (string)$report['report_no'],
        'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
        'status' => 'pending',
        'last_error' => '',
        'updated_at' => $now,
    ];
    if ($existing) {
        DB::update('integration_order_pushes', $data, 'id = ?', [(int)$existing['id']]);
    } else {
        DB::insert('integration_order_pushes', array_merge($data, [
            'biz_type' => $bizType,
            'biz_id' => (int)$reportId,
            'retry_count' => 0,
            'created_at' => $now,
        ]));
    }
    return true;
}

/**
 * 组装推送载荷：患者 + 住院就诊 + 申请单（单价取项目库，缺失按 0）
 * @return array|null 无身份证/无住院号返回 null（无法门诊建档）
 */
function opd_order_push_payload($bizType, array $report) {
    $patient = DB::selectOne("SELECT * FROM patients WHERE id = ?", [(int)$report['patient_id']]);
    if (!$patient || trim((string)$patient['admission_no']) === '') return null;

    $deptName = '';
    if (!empty($patient['department_id'])) {
        $d = DB::selectOne("SELECT name FROM departments WHERE id = ?", [(int)$patient['department_id']]);
        if ($d) $deptName = (string)$d['name'];
    }
    $doctorName = '';
    if (!empty($report['created_by'])) {
        $u = DB::selectOne("SELECT name FROM users WHERE id = ?", [(int)$report['created_by']]);
        if ($u) $doctorName = (string)$u['name'];
    }

    if ($bizType === 'exam') {
        $cat = DB::selectOne("SELECT price, category, sub_category FROM exam_catalog WHERE name = ? LIMIT 1", [(string)$report['exam_type']]);
        $sub = $cat ? trim((string)$cat['sub_category']) : '';
        $category = $sub !== '' ? $sub : ($cat ? (string)$cat['category'] : (string)$report['body_part']);
        $order = [
            'order_no' => (string)$report['report_no'],
            'order_type' => 'imaging',
            'item_name' => (string)$report['exam_type'],
            'category' => $category,
            'price' => $cat ? (float)$cat['price'] : 0,
            'created_at' => TimeHelper::isoUtc($report['created_at']),   // 对外接口契约：标准 UTC（ISO 8601 带 Z）
        ];
    } else {
        $cat = DB::selectOne("SELECT price, category FROM lab_catalog WHERE name = ? LIMIT 1", [(string)$report['exam_name']]);
        $order = [
            'order_no' => (string)$report['report_no'],
            'order_type' => 'lab',
            'item_name' => (string)$report['exam_name'],
            'category' => $cat ? (string)$cat['category'] : '',
            'price' => $cat ? (float)$cat['price'] : 0,
            'created_at' => TimeHelper::isoUtc($report['created_at']),   // 对外接口契约：标准 UTC（ISO 8601 带 Z）
        ];
    }

    return [
        'patient' => [
            'id_card' => (string)$patient['id_card'],
            'name' => (string)$patient['name'],
            'gender' => (string)$patient['gender'],
            'birth_date' => (string)$patient['birth_date'],
        ],
        'visit' => [
            'patient_class' => 'I',
            'admission_no' => (string)$patient['admission_no'],
            'apply_dept' => $deptName,
            'apply_doctor' => $doctorName,
        ],
        'order' => $order,
        'paid' => true,
    ];
}

/**
 * 处理待推送/失败队列（单次限量，失败累计重试次数，最多 5 次后不再自动重试）
 * @param int  $limit 单次最多处理条数
 * @param bool $force 手动重推：忽略重试次数上限（接口管理页「重推失败单据」）
 * @return array { processed, success, failed }
 */
function opd_order_push_process($limit = 20, $force = false) {
    $limit = max(1, min(500, (int)$limit));
    $res = ['processed' => 0, 'success' => 0, 'failed' => 0];
    if (!opd_order_push_enabled()) return $res;

    $rows = DB::select(
        "SELECT * FROM integration_order_pushes WHERE status IN ('pending','failed')"
        . ($force ? '' : ' AND retry_count < 5')
        . " ORDER BY id ASC LIMIT " . $limit
    );
    foreach ($rows as $row) {
        $res['processed']++;
        $payload = json_decode((string)$row['payload'], true);
        // 兼容升级前入队的旧载荷：时间若为「纯墙钟串」（无 T/Z/偏移）→ 按本地墙钟换算为 UTC ISO
        if (is_array($payload) && isset($payload['order']['created_at'])
            && preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', (string)$payload['order']['created_at'])) {
            $payload['order']['created_at'] = TimeHelper::isoUtc(TimeHelper::fromLocal($payload['order']['created_at']));
        }
        if (!is_array($payload)) {
            DB::update('integration_order_pushes', [
                'status' => 'failed',
                'retry_count' => (int)$row['retry_count'] + 1,
                'last_error' => '载荷损坏',
                'updated_at' => DateHelper::now(),
            ], 'id = ?', [(int)$row['id']]);
            $res['failed']++;
            continue;
        }
        $r = opd_his_push_orders($payload);
        if ($r['ok']) {
            DB::update('integration_order_pushes', [
                'status' => 'success', 'last_error' => '', 'updated_at' => DateHelper::now(),
            ], 'id = ?', [(int)$row['id']]);
            $res['success']++;
        } else {
            $msg = trim((string)$r['msg']);
            if ($msg === '') $msg = 'HTTP ' . $r['http'];
            DB::update('integration_order_pushes', [
                'status' => 'failed',
                'retry_count' => (int)$row['retry_count'] + 1,
                'last_error' => mb_substr($msg, 0, 500),
                'updated_at' => DateHelper::now(),
            ], 'id = ?', [(int)$row['id']]);
            $res['failed']++;
        }
    }
    return $res;
}

/** 队列状态统计（接口管理页展示） */
function opd_order_push_counts() {
    $out = ['pending' => 0, 'failed' => 0, 'success' => 0, 'total' => 0];
    foreach (DB::select("SELECT status, COUNT(*) AS c FROM integration_order_pushes GROUP BY status") as $r) {
        $st = (string)$r['status'];
        if (isset($out[$st])) $out[$st] = (int)$r['c'];
    }
    $out['total'] = $out['pending'] + $out['failed'] + $out['success'];
    return $out;
}
