<?php
// api/modules/nurse/vitals/chart.php - 电子护理体温单 (三测单) 数据组装
// 参数: demo=1 → 内置 7 天演示数据集 (chart_demo.php, 覆盖全部绘图场景, 用于功能验证/培训);
//       patient_id (+start YYYY-MM-DD, 缺省最近 7 天) → 从护理记录单派生真实数据。
// 输出 JSON 结构 (TempChart 渲染契约):
//   patient: {name, gender, birth_date, bed_no, admission_no, department_name, ward_name, admission_date}
//   start:   窗口起始日 (YYYY-MM-DD); days: 7 天数组:
//     { date, day_no (住院天数), surgery_days (术后天数串, 红), slots: [6], daily: {...} }
//     slots[i] (对应 2/6/10/14/18/22 点): {
//       temp: {v, type: oral|axillary|rectal|ear, verified: 复试v, cooling: 降温后复测值, no_rise: 体温不升} | null
//       pulse, hr (心率, 与脉搏并存且 hr>pulse 时绘短绌斜线), breath, pain,
//       pacemaker (起搏器→带H红圈), ventilator (呼吸机→®), event (顶部红字事件), unmeasured (拒测/外出→底部竖排+断线)
//     }
//     daily: {stool(支持0/E·3/2E·※·☆), bp_am, bp_pm (收缩压/舒张压), intake, output, urine,
//             height, weight, skin_test(阳性红/阴性黑), allergy_drugs, special_drugs, other}
//   说明: 护理记录单暂无 体温测量途径/心率/疼痛/降温复测/术后天数/大便 等字段,
//         真实模式按缺省口径输出 (途径默认腋表), 相关列留白待字段扩展。
Auth::requireRole(['admin', 'nurse', 'doctor']);
require_once __DIR__ . '/chart_demo.php';

if (Validator::get('demo')) {
    Response::success(temperature_chart_demo_data());
}

$patientId = (int)Validator::get('patient_id', 0);
if (!$patientId) Response::error('请输入患者ID');
if (!can_access_patient(Auth::user(), $patientId)) Response::error('无权访问该患者', 403);

$start = trim((string)Validator::get('start', ''));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start)) $start = date('Y-m-d', strtotime('-6 days'));
$end = date('Y-m-d', strtotime($start . ' +7 days'));

$patient = DB::selectOne(
    "SELECT p.id, p.name, p.gender, p.birth_date, p.bed_no, p.admission_no, p.admission_date,
            d.name AS department_name, w.name AS ward_name
     FROM patients p
     LEFT JOIN departments d ON p.department_id = d.id
     LEFT JOIN wards w ON p.ward_id = w.id
     WHERE p.id = ?", [$patientId]);
if (!$patient) Response::error('患者不存在');

$notes = DB::select(
    "SELECT record_time, temperature, pulse, respiratory_rate, bp_systolic, bp_diastolic,
            intake_ml, output_ml, height, weight
     FROM nursing_notes
     WHERE patient_id = ? AND record_time >= ? AND record_time < ?
       AND (temperature IS NOT NULL OR pulse IS NOT NULL OR respiratory_rate IS NOT NULL
            OR bp_systolic IS NOT NULL OR intake_ml IS NOT NULL OR output_ml IS NOT NULL
            OR height IS NOT NULL OR weight IS NOT NULL)
     ORDER BY record_time ASC, id ASC",
    [$patientId, TimeHelper::fromLocal($start . ' 00:00:00'), TimeHelper::fromLocal($end . ' 00:00:00')]);

/** 空槽位模板 */
function temperature_chart_empty_slot() {
    return ['temp' => null, 'pulse' => null, 'hr' => null, 'breath' => null, 'pain' => null,
        'pacemaker' => false, 'ventilator' => false, 'event' => '', 'unmeasured' => ''];
}

/** 测量时刻 → 6 时点槽位序号 (取最近时点) */
function temperature_chart_slot_index($hour) {
    $slots = [2, 6, 10, 14, 18, 22];
    $best = 0; $bestDist = PHP_INT_MAX;
    foreach ($slots as $i => $s) {
        $d = abs($hour - $s);
        if ($d < $bestDist) { $bestDist = $d; $best = $i; }
    }
    return $best;
}

/** 住院天数 (入院当日为第 1 天; 未入科返回空) */
function temperature_chart_day_no($admissionDate, $date) {
    if (!$admissionDate) return '';
    $d = floor((strtotime($date) - strtotime(substr($admissionDate, 0, 10))) / 86400) + 1;
    return $d >= 1 ? (int)$d : '';
}

/* ==================== 手术自动识别 (术后天数 + 手术事件) ==================== */
/* 只计未取消且关联手术医嘱有效 (未作废) 的手术; 择期次日为术后第 1 天, 急诊/日间当天为第 1 天 */

function temperature_chart_roman($n) {
    $map = [1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V', 6 => 'VI', 7 => 'VII', 8 => 'VIII', 9 => 'IX', 10 => 'X'];
    return $map[(int)$n] ?? (string)$n;
}

/** 术后天数串: 1 次在效 → "5"/"II-5"; ≥2 次在效 → 分式 "II-2/4" (分子=最近一次术后天数, 分母=最早一次) */
function temperature_chart_surgery_days(array $active) {
    if (!$active) return '';
    if (count($active) === 1) {
        $a = $active[0];
        return $a['idx'] > 1 ? temperature_chart_roman($a['idx']) . '-' . $a['days'] : (string)$a['days'];
    }
    $newest = $active[count($active) - 1];
    $oldest = $active[0];
    $num = $newest['idx'] > 1 ? temperature_chart_roman($newest['idx']) . '-' . $newest['days'] : (string)$newest['days'];
    return $num . '/' . $oldest['days'];
}

/* 时间汉字化: 11:40 → 十一时四十分; 08:00 → 零八时零零分 */
function temperature_chart_cn_num($n) {
    $c = ['零', '一', '二', '三', '四', '五', '六', '七', '八', '九'];
    $n = (int)$n;
    if ($n < 10) return '零' . $c[$n];
    if ($n === 10) return '十';
    if ($n < 20) return '十' . ($n % 10 ? $c[$n % 10] : '');
    return $c[intdiv($n, 10)] . '十' . ($n % 10 ? $c[$n % 10] : '');
}
function temperature_chart_cn_time($timeStr) {
    if (strlen($timeStr) < 16) return '';
    return temperature_chart_cn_num(substr($timeStr, 11, 2)) . '时' . temperature_chart_cn_num(substr($timeStr, 14, 2)) . '分';
}

/** 事件写入: 按本地墙钟时间定位日期列×时点列, 红字竖排 */
function temperature_chart_set_event(&$days, $start, $localTimeStr, $text) {
    if (strlen($localTimeStr) < 10) return;
    $idx = (int)floor((strtotime(substr($localTimeStr, 0, 10)) - strtotime($start)) / 86400);
    if ($idx < 0 || $idx > 6) return;
    $hour = (int)substr($localTimeStr, 11, 2);
    $days[$idx]['slots'][temperature_chart_slot_index($hour)]['event'] = $text;
}

/* 手术列表: 未取消且关联手术医嘱有效 (未作废); planned_at/surgery_date 为本地墙钟业务字段 */
$surgeryRows = DB::select(
    "SELECT s.category, COALESCE(NULLIF(s.planned_at, ''), NULLIF(s.surgery_date, '')) AS surgery_time
     FROM surgeries s
     LEFT JOIN orders o ON o.id = s.order_id
     WHERE s.patient_id = ? AND s.status = 'requested'
       AND (s.order_id IS NULL OR s.order_id = 0 OR COALESCE(o.status, '') NOT IN ('invalid', 'invalid_pending'))
     ORDER BY COALESCE(NULLIF(s.planned_at, ''), NULLIF(s.surgery_date, '')) ASC, s.id ASC",
    [$patientId]);
$surgeryList = [];
foreach ($surgeryRows as $sr) {
    $t = trim((string)$sr['surgery_time']);
    if ($t === '') continue;
    /* 择期: 手术次日为术后第 1 天; 急诊/日间: 手术当天为第 1 天 */
    $startDate = $sr['category'] === 'elective'
        ? date('Y-m-d', strtotime(substr($t, 0, 10) . ' +1 day'))
        : substr($t, 0, 10);
    $surgeryList[] = ['idx' => count($surgeryList) + 1, 'start' => strtotime($startDate), 'time' => $t];
}

$days = [];
for ($i = 0; $i < 7; $i++) {
    $date = date('Y-m-d', strtotime($start . " +$i days"));
    $dayTs = strtotime($date);
    $active = [];
    foreach ($surgeryList as $sg) {
        $diff = (int)floor(($dayTs - $sg['start']) / 86400) + 1;
        if ($diff >= 1 && $diff <= 14) $active[] = ['idx' => $sg['idx'], 'days' => $diff];
    }
    $days[] = [
        'date' => $date,
        'day_no' => temperature_chart_day_no(TimeHelper::localDate($patient['admission_date']), $date),
        'surgery_days' => temperature_chart_surgery_days($active),
        'slots' => array_map('temperature_chart_empty_slot', array_fill(0, 6, null)),
        'daily' => ['stool' => '', 'bp_am' => '', 'bp_pm' => '', 'intake' => '', 'output' => '', 'urine' => '',
            'height' => '', 'weight' => '', 'skin_test' => '', 'allergy_drugs' => '',
            'special_drugs' => '', 'other' => ''],
    ];
}

/* 逐条护理记录 → 时点槽位: 存储时间按站点时区换算后定位 (修复 UTC 存储模式跨日/跨时点偏移);
   同槽位同字段取最新一次 (多次测量以最新为准, 历史明细看护理记录单); 血压按午前/午后取最新; 出入量按日累计 */
$intake = []; $output = [];
foreach ($notes as $n) {
    $lt = TimeHelper::local($n['record_time']);
    if (strlen($lt) < 10) continue;
    $idx = (int)floor((strtotime(substr($lt, 0, 10)) - strtotime($start)) / 86400);
    if ($idx < 0 || $idx > 6) continue;
    $day = &$days[$idx];
    $hour = (int)substr($lt, 11, 2);
    $s = &$day['slots'][temperature_chart_slot_index($hour)];
    if ($n['temperature'] !== null) {
        $s['temp'] = ['v' => round((float)$n['temperature'], 1), 'type' => 'axillary',
            'verified' => false, 'cooling' => null, 'no_rise' => false];
    }
    if ($n['pulse'] !== null) $s['pulse'] = (int)$n['pulse'];
    if ($n['respiratory_rate'] !== null) $s['breath'] = (int)$n['respiratory_rate'];
    if ($n['bp_systolic'] !== null && $n['bp_diastolic'] !== null) {
        $bp = (int)$n['bp_systolic'] . '/' . (int)$n['bp_diastolic'];
        if ($hour < 12) $day['daily']['bp_am'] = $bp; else $day['daily']['bp_pm'] = $bp;
    }
    if ($n['intake_ml'] !== null) $intake[$idx] = ($intake[$idx] ?? 0) + (float)$n['intake_ml'];
    if ($n['output_ml'] !== null) $output[$idx] = ($output[$idx] ?? 0) + (float)$n['output_ml'];
    if ($n['height'] !== null && $day['daily']['height'] === '') $day['daily']['height'] = rtrim(rtrim(number_format((float)$n['height'], 1), '0'), '.');
    if ($n['weight'] !== null && $day['daily']['weight'] === '') $day['daily']['weight'] = rtrim(rtrim(number_format((float)$n['weight'], 1), '0'), '.');
    unset($day, $s);
}
foreach ($days as $i => $d) {
    if ($intake[$i] > 0) $days[$i]['daily']['intake'] = rtrim(rtrim(number_format($intake[$i], 1), '0'), '.');
    if ($output[$i] > 0) $days[$i]['daily']['output'] = rtrim(rtrim(number_format($output[$i], 1), '0'), '.');
}

/* ==================== 特殊事件自动标注 (红字竖排, 全部汉字) ==================== */
/* 入院: 入科时间 (患者拉入病区时刻, admission_date 为存储时间 → 本地换算); 手术: 手术时间 (本地墙钟) */
if (!empty($patient['admission_date'])) {
    $admitLocal = TimeHelper::local($patient['admission_date']);
    $admitText = temperature_chart_cn_time($admitLocal);
    if ($admitText !== '') temperature_chart_set_event($days, $start, $admitLocal, '入院时间' . $admitText);
}
foreach ($surgeryList as $sg) {
    $surgeryText = temperature_chart_cn_time($sg['time']);
    if ($surgeryText !== '') temperature_chart_set_event($days, $start, $sg['time'], '手术时间' . $surgeryText);
}

/* 翻页可用性: 窗口外是否存在更早/更晚体征记录 */
$hasOlder = (bool)DB::selectOne("SELECT id FROM nursing_notes WHERE patient_id = ? AND record_time < ? LIMIT 1",
    [$patientId, TimeHelper::fromLocal($start . ' 00:00:00')]);
$hasNewer = (bool)DB::selectOne("SELECT id FROM nursing_notes WHERE patient_id = ? AND record_time >= ? LIMIT 1",
    [$patientId, TimeHelper::fromLocal($end . ' 00:00:00')]);

Response::success([
    'mode' => 'real',
    'patient' => $patient,
    'start' => $start,
    'end' => date('Y-m-d', strtotime($start . ' +6 days')),
    'days' => $days,
    'has_older' => $hasOlder,
    'has_newer' => $hasNewer,
]);
