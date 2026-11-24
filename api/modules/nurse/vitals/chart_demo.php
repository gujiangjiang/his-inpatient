<?php
// api/modules/nurse/vitals/chart_demo.php - 体温单 (三测单) 内置 7 天演示数据集 (非路由, chart.php 引用)
// 数据窗口 2023-12-29 → 2024-01-04 (跨年+跨月), 覆盖全部绘图场景:
//   降温复测红圈红虚线 / 脉搏短绌斜线填充 / 起搏器 H / 体温脉搏呼吸多重叠 / 心率与体温重叠 H 标注 /
//   拒测·外出未测断线 / 35℃下箭头 / "不升" / >42℃·>180次上箭头 / 复试 v / 呼吸机 ® /
//   疼痛评分曲线 / 特殊大便符号 (0/E·3/2E·※·☆) / 血压分上下午 / 皮试红阳性黑阴性 /
//   术后天数分式 (1/3) 与罗马前缀 (II-2/4) / 特殊事件纵向标注 (入院·手术·输血·请假·出院)
// 字段说明见 chart.php 头注释; 此数据仅用于演示与绘图自检, 不写入业务库。

function temperature_chart_demo_data() {
    /* 槽位速记: t=体温[v,type,verified,cooling,no_rise] p=脉搏 h=心率 b=呼吸 pn=疼痛 */
    $slot = function ($t = null, $p = null, $b = null, $extra = []) {
        return array_merge([
            'temp' => $t === null ? null : array_merge(['v' => $t, 'type' => 'oral', 'verified' => false, 'cooling' => null, 'no_rise' => false], is_array($t) ? $t : []),
            'pulse' => $p, 'hr' => null, 'breath' => $b, 'pain' => null,
            'pacemaker' => false, 'ventilator' => false, 'event' => '', 'unmeasured' => '',
        ], $extra);
    };
    $daily = function ($over = []) {
        return array_merge(['stool' => '', 'bp_am' => '', 'bp_pm' => '', 'intake' => '', 'output' => '',
            'urine' => '', 'height' => '', 'weight' => '', 'skin_test' => '',
            'allergy_drugs' => '', 'special_drugs' => '', 'other' => ''], $over);
    };

    return [
        'mode' => 'demo',
        'patient' => [
            'name' => '张三', 'gender' => '男', 'age' => '45 岁', 'bed_no' => '12',
            'admission_no' => 'A20231229001', 'department_name' => '心内科', 'ward_name' => '心内一病区',
            'admission_date' => '2023-12-29', 'doc_name' => '体温单',
        ],
        'start' => '2023-12-29',
        'days' => [
            [ /* ---- 第1日 2023-12-29 入院 ---- */
                'date' => '2023-12-29', 'day_no' => 1, 'surgery_days' => '',
                'slots' => [
                    $slot(), $slot(),
                    $slot(['v' => 36.8, 'type' => 'oral'], 76, 18),                       // 体温+脉搏重叠: 蓝点外包红圈
                    $slot(37.0, 80, 19, ['event' => '入院']),
                    $slot(36.6, 78, 18, ['pain' => 5]),
                    $slot(36.4, 76, 17),
                ],
                'daily' => $daily(['stool' => '0', 'bp_am' => '132/86', 'bp_pm' => '128/84',
                    'intake' => '800', 'output' => '650', 'urine' => '650', 'height' => '170', 'weight' => '65']),
            ],
            [ /* ---- 第2日 12-30 手术日 (35℃ 下箭头 / 肛表+脉搏重叠 / 皮试阴性) ---- */
                'date' => '2023-12-30', 'day_no' => 2, 'surgery_days' => '',
                'slots' => [
                    $slot(36.5, 80, 18),
                    $slot(['v' => 34.6, 'type' => 'rectal'], 70, 16),                    // <35℃: 35℃处蓝点+向下箭头
                    $slot(null, null, null, ['event' => '手术']),
                    $slot(['v' => 38.5, 'type' => 'rectal'], 100, 22),                   // 直肠+脉搏重叠: 蓝圈内红点
                    $slot(['v' => 38.1, 'type' => 'rectal'], 96, 20, ['pain' => 6]),
                    $slot(['v' => 37.8, 'type' => 'rectal'], 92, 19),
                ],
                'daily' => $daily(['stool' => '1', 'bp_am' => '118/76', 'bp_pm' => '115/72',
                    'intake' => '2200', 'output' => '1500', 'urine' => '1400',
                    'skin_test' => '阴性', 'special_drugs' => '12/30接种100']),
            ],
            [ /* ---- 第3日 12-31 术后第1天 (降温复测 / >42℃·>180次 上箭头 / 灌肠 0/E) ---- */
                'date' => '2023-12-31', 'day_no' => 3, 'surgery_days' => '1',
                'slots' => [
                    $slot(['v' => 37.6, 'type' => 'rectal'], 90, 19),
                    $slot(['v' => 37.4, 'type' => 'rectal'], 88, 18),
                    $slot(['v' => 37.2, 'type' => 'rectal'], 86, 18, ['pain' => 4]),
                    $slot(['v' => 39.2, 'type' => 'rectal', 'cooling' => 38.1], 104, 22, ['event' => '输血400']), // 降温半小时复测: 同格红圈+红虚线
                    $slot(['v' => 42.3, 'type' => 'rectal'], 186, 26),                   // >42℃ / >180次: 上箭头
                    $slot(['v' => 40.6, 'type' => 'rectal'], 128, 24),
                ],
                'daily' => $daily(['stool' => '0/E', 'bp_am' => '126/80', 'bp_pm' => '122/78',
                    'intake' => '1800', 'output' => '1200', 'urine' => '1100']),
            ],
            [ /* ---- 第4日 2024-01-01 跨年 (脉搏短绌斜线 / 起搏器 H / 呼吸机 ® / 皮试阳性红字) ---- */
                'date' => '2024-01-01', 'day_no' => 4, 'surgery_days' => '2',
                'slots' => [
                    $slot(['v' => 39.0, 'type' => 'rectal'], 110, 22),
                    $slot(['v' => 37.0, 'type' => 'axillary'], 84, 19),                  // 腋下+脉搏重叠: 蓝叉外红圈
                    $slot(['v' => 37.2, 'type' => 'axillary'], 96, 20, ['hr' => 118]),   // 脉搏短绌起点 (hr>pulse)
                    $slot(['v' => 37.6, 'type' => 'axillary'], 98, 16, ['hr' => 124, 'ventilator' => true]), // 短绌+呼吸机®
                    $slot(['v' => 37.8, 'type' => 'axillary'], null, 18, ['hr' => 90, 'pacemaker' => true, 'pain' => 2]), // 起搏器: 带 H 红圈
                    $slot(['v' => 37.4, 'type' => 'axillary'], 88, 18),
                ],
                'daily' => $daily(['stool' => '3/2E', 'bp_am' => '136/88', 'bp_pm' => '134/86',
                    'intake' => '1600', 'output' => '1300', 'urine' => '1200',
                    'skin_test' => '阳性', 'allergy_drugs' => '青霉素']),
            ],
            [ /* ---- 第5日 01-02 二次手术 (拒测/外出断线 + 请假事件 / 呼吸+脉搏重叠 / 大便失禁※) ---- */
                'date' => '2024-01-02', 'day_no' => 5, 'surgery_days' => '1/3',
                'slots' => [
                    $slot(['v' => 37.8, 'type' => 'axillary'], 92, 20),
                    $slot(null, null, null, ['unmeasured' => '拒测']),                   // 未测: 底部蓝字, 曲线断开
                    $slot(['v' => 37.2, 'type' => 'axillary'], 88, 20),                  // 呼吸与脉搏同行重叠: 呼吸符号外红圈
                    $slot(null, null, null, ['event' => '请假', 'unmeasured' => '外出']), // 顶部请假事件 + 底部外出红字
                    $slot(['v' => 37.0, 'type' => 'axillary'], 86, 18, ['pain' => 7]),
                    $slot(['v' => 36.8, 'type' => 'axillary'], 84, 17),
                ],
                'daily' => $daily(['stool' => '※', 'bp_am' => '120/78', 'bp_pm' => '118/76',
                    'intake' => '1200', 'output' => '900', 'urine' => '850']),
            ],
            [ /* ---- 第6日 01-03 (复试 v / 心率与体温重叠 H / 拒测 / 人工肛门☆ / 罗马前缀) ---- */
                'date' => '2024-01-03', 'day_no' => 6, 'surgery_days' => 'II-2/4',
                'slots' => [
                    $slot(['v' => 36.6, 'type' => 'axillary'], 82, 17),
                    $slot(['v' => 36.8, 'type' => 'axillary'], 84, 18),
                    $slot(['v' => 37.0, 'type' => 'axillary'], null, 18, ['hr' => 92]),  // 心率与体温重叠: 标注 H
                    $slot(['v' => 39.0, 'type' => 'axillary', 'verified' => true], 108, 21), // 体温突变复测无误: 符号上方 v
                    $slot(null, null, null, ['unmeasured' => '拒测']),
                    $slot(['v' => 36.5, 'type' => 'axillary'], 80, 17, ['pain' => 3]),
                ],
                'daily' => $daily(['stool' => '☆', 'bp_am' => '124/80', 'bp_pm' => '120/78',
                    'intake' => '1500', 'output' => '1100', 'urine' => '1000', 'other' => '伤口换药']),
            ],
            [ /* ---- 第7日 01-04 出院日 (不升 / 出院事件) ---- */
                'date' => '2024-01-04', 'day_no' => 7, 'surgery_days' => 'II-3/5',
                'slots' => [
                    $slot(['v' => 36.4, 'type' => 'axillary'], 78, 16),
                    $slot(['v' => 33.8, 'type' => 'axillary', 'no_rise' => true], 68, 15), // 体温不升: 35℃下方蓝字"不升"
                    $slot(['v' => 36.6, 'type' => 'axillary'], 80, 17, ['pain' => 1]),
                    $slot(['v' => 36.5, 'type' => 'axillary'], 78, 16),
                    $slot(null, null, null, ['event' => '出院']),
                    $slot(),
                ],
                'daily' => $daily(['stool' => '2', 'bp_am' => '122/78', 'bp_pm' => '120/76',
                    'intake' => '900', 'output' => '700', 'urine' => '650']),
            ],
        ],
    ];
}
