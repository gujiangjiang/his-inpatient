<?php
// api/modules/orders/set_apply.php - 套用医嘱组套 (生成草稿医嘱)
Auth::requireRole(['admin', 'doctor']);
$data = Validator::all();
$setId = Validator::get('set_id');
$patientId = Validator::get('patient_id');

if (!$setId || !$patientId) Response::error('缺少组套或患者');

$set = DB::selectOne("SELECT * FROM order_sets WHERE id = ? AND is_active = 1", [$setId]);
if (!$set) Response::error('组套不存在');
$patient = DB::selectOne("SELECT * FROM patients WHERE id = ?", [$patientId]);
if (!$patient) Response::error('患者不存在');
if (!is_patient_admitted($patientId)) Response::error('患者尚未入科（未指派主管医师），不能开医嘱');

$clientItems = Validator::get('items');
if (is_array($clientItems) && count($clientItems)) {
    /* 开嘱端勾选/修改后的医嘱 (group_seq: 0=独立; 相同正数=同一组) */
    $items = [];
    foreach ($clientItems as $it) {
        if (empty($it['content'])) continue;
        $items[] = [
            'category' => ($it['category'] ?? 'nonmedication'),
            'drug_id' => $it['drug_id'] ?? null,
            'content' => (string)$it['content'],
            'dosage' => (string)($it['dosage'] ?? ''),
            'dosage_unit' => (string)($it['dosage_unit'] ?? ''),
            'usage_name' => (string)($it['usage_name'] ?? ''),
            'usage_rate' => (string)($it['usage_rate'] ?? ''),
            'freq_code' => (string)($it['freq_code'] ?? ''),
            'freq_detail' => (string)($it['freq_detail'] ?? ''),
            'order_period' => ($it['order_period'] === 'temp') ? 'temp' : 'long',
            'group_seq' => (int)($it['group_seq'] ?? 0),
            'notes' => (string)($it['notes'] ?? ''),
        ];
    }
    if (!$items) Response::error('请至少勾选一条医嘱');
    /* 服务端兜底: 引用快照以库内组套为准 (客户端勾选可能未回传 ref 字段) */
    $stored = DB::select("SELECT * FROM order_set_items WHERE set_id = ? ORDER BY sort_no ASC, id ASC", [$setId]);
    foreach ($items as &$it) {
        foreach ($stored as $st) {
            $sameDrug = !empty($st['drug_id']) && (int)$st['drug_id'] === (int)($it['drug_id'] ?? 0);
            $sameText = empty($st['drug_id']) && (string)$st['content'] === (string)$it['content'] && (string)$st['category'] === (string)$it['category'];
            if ($sameDrug || $sameText) {
                $it['ref_id'] = $st['ref_id'] ?? null;
                $it['ref_name'] = (string)($st['ref_name'] ?? '');
                $it['ref_spec'] = (string)($st['ref_spec'] ?? '');
                $it['ref_manufacturer'] = (string)($st['ref_manufacturer'] ?? '');
                break;
            }
        }
    }
    unset($it);
} else {
    $items = DB::select("SELECT * FROM order_set_items WHERE set_id = ? ORDER BY sort_no ASC, id ASC", [$setId]);
}
if (!$items) Response::error('组套内容为空');

/* 可用性校验: 已下架/变更的医嘱不可套用 (前端已禁选, 服务端兜底) */
$checkedAvail = order_set_items_with_avail($items);
$badItems = array_filter($checkedAvail, function ($i) { return empty($i['avail']['ok']); });
if ($badItems) {
    $names = array_map(function ($i) { return $i['content'] . '（' . $i['avail']['reason'] . '）'; }, array_slice($badItems, 0, 3));
    Response::error('组套内存在已下架/变更的医嘱：' . implode('、', $names) . '，请先编辑组套更新后再套用');
}
$items = $checkedAvail;

$userId = Auth::user()['id'];
$now = DateHelper::now();
$status = (int)Validator::get('submit', 0) ? 'pending' : 'draft';
/* 成组判定: 客户端勾选成组 (相同 group_seq>0) 按组分; 兼容旧组套 (无分组的药品组套整体成组) */
$hasGroupSeq = is_array($clientItems) && count($clientItems);
$legacyGroup = !$hasGroupSeq && count($items) > 1 && $set['category'] === 'medication';

$pdo = DB::getPDO();
$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare("INSERT INTO orders (patient_id, admission_no, parent_id, is_group_main, order_type, order_period,
                order_no, content, category, status, drug_id, dosage, dosage_unit, usage_name, usage_rate,
                freq_code, freq_detail, billing_flags, group_seq, sort_no, start_date, created_by, created_at, updated_at)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
    $mainId = null;
    $ids = [];
    $groupMain = []; /* group_seq>0 → 该组主医嘱 id (首个) */
    foreach ($items as $i => $item) {
        $orderNo = doc_no('O');
        $itemPeriod = $item['order_period'] ?: 'long';
        $freqCode = $item['freq_code'];
        $freqDetail = $item['freq_detail'];
        // 临时医嘱无频次/执行时间
        if ($itemPeriod === 'temp') { $freqCode = ''; $freqDetail = ''; }
        $gseq = (int)($item['group_seq'] ?? 0);
        $inGroup = $legacyGroup || $gseq > 0;
        $parent = null;
        $isMain = 0;
        if ($inGroup) {
            if ($legacyGroup) {
                $parent = ($i > 0) ? $mainId : null;
                $isMain = ($i === 0) ? 1 : 0;
            } else {
                $key = 'g' . $gseq;
                if (isset($groupMain[$key])) { $parent = $groupMain[$key]; }
                else { $groupMain[$key] = -1; $isMain = 1; }
            }
        }
        $stmt->execute([
            $patientId, $patient['admission_no'],
            $parent,
            $isMain,
            ($item['category'] === 'medication') ? 'medication' : 'general',
            $itemPeriod,
            $orderNo, $item['content'], $item['category'], $status,
            $item['drug_id'], $item['dosage'], $item['dosage_unit'],
            $item['usage_name'], $item['usage_rate'],
            $freqCode, $freqDetail, '',
            $gseq, 0, $now, $userId, $now, $now
        ]);
        $id = $pdo->lastInsertId();
        if (!empty($item['notes'])) {
            DB::execute("UPDATE orders SET notes = ? WHERE id = ?", [(string)$item['notes'], $id]);
        }
        $ids[] = $id;
        if ($i === 0) $mainId = $id;
        if ($inGroup && !$legacyGroup && $isMain) $groupMain['g' . $gseq] = $id;
    }
    $pdo->commit();
    Response::success(['id' => $mainId, 'ids' => $ids, 'count' => count($ids)]);
} catch (Exception $e) {
    $pdo->rollback();
    Response::error('套用组套失败：' . $e->getMessage());
}
