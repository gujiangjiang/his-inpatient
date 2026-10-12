<?php
// api/migrations/php/031_auto_order_flag.php - 历史申请类医嘱识别与统一提交
// 说明: 检验/检查/会诊申请自动生成的医嘱属"申请类"(is_auto_generated=1),
//       不参与周期切换/剂量/途径/频次/执行时间编辑; 历史草稿态一并提交为待核对
return [
    'version' => '031',
    'description' => '回填申请类医嘱 is_auto_generated 标记并统一提交历史草稿',
    'up' => function($m) {
        if (!$m->hasTable('orders') || !$m->hasColumn('orders', 'is_auto_generated')) return;

        // 会诊: 通过会诊单关联精确识别
        if ($m->hasTable('consultations')) {
            $m->execute("UPDATE orders SET is_auto_generated = 1 WHERE id IN (SELECT order_id FROM consultations WHERE order_id IS NOT NULL AND order_id > 0)");
        }
        // 检验/检查: 非药品医嘱中按项目库名称匹配
        if ($m->hasTable('lab_catalog')) {
            $m->execute("UPDATE orders SET is_auto_generated = 1 WHERE category = 'nonmedication' AND is_auto_generated = 0 AND content IN (SELECT name FROM lab_catalog)");
        }
        if ($m->hasTable('exam_catalog')) {
            $m->execute("UPDATE orders SET is_auto_generated = 1 WHERE category = 'nonmedication' AND is_auto_generated = 0 AND content IN (SELECT name FROM exam_catalog)");
        }
        // 历史申请类草稿统一提交 (与"申请类默认提交"语义一致)
        $m->execute("UPDATE orders SET status = 'pending' WHERE is_auto_generated = 1 AND status = 'draft'");
    }
];
