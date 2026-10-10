<?php
// api/core/helpers/patient_helper.php - 患者卡片派生数据

/** 护理等级内容 → 卡片等级值 */
const NURSING_LEVEL_MAP = [
    '特级护理' => 'special',
    '一级护理' => '1',
    '二级护理' => '2',
    '三级护理' => '3',
];

/**
 * 以最新开具的有效护理医嘱覆盖卡片护理等级
 * (未停止/未作废, 按开单顺序取最新: 如忘停一级护理又开了二级, 显示二级)
 */
function apply_latest_nursing_level(array &$patients) {
    foreach ($patients as &$p) {
        $latest = DB::selectOne(
            "SELECT content FROM orders WHERE patient_id = ? AND category != 'medication'
             AND status NOT IN ('stopped', 'invalid')
             AND content IN ('特级护理', '一级护理', '二级护理', '三级护理')
             ORDER BY id DESC LIMIT 1",
            [$p['id']]
        );
        if ($latest && isset(NURSING_LEVEL_MAP[$latest['content']])) {
            $p['nursing_level'] = NURSING_LEVEL_MAP[$latest['content']];
        }
    }
    unset($p);
    return $patients;
}
