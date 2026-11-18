<?php
// api/migrations/php/064_dual_pharmacy.php - 中心/门诊双药房: 药房归属 + 预摆药 + 扣费 + 发药 + 执行预留
// 说明：本迁移为 HIS 药房体系从「单药房雏形」升级为「中心药房+门诊药房」全流程：
//  - orders 增加药房归属(pharmacy_type/outpatient)与摆药/发药/执行状态机
//    (dispense_status) + 扣费(charge_amount/charged_at) + 执行预留(plan_time/batch)
//  - medications 对齐门诊拆零/规格口径（spec_pack_qty/warn_qty/allow_split），
//    便于自动算量与低库存告警
//  - dispensing_records 增加 stage（prepared 预摆/ dispensed 已摆已发/ delivered 已送达）
//  - 新增 charge_records 扣费流水表（摆药时真正扣 prepay_fee 落账）
//  - 新增 opd_drug_cache 门诊药房降级缓存表（实时查失败时用，标注离线缓存）
// 存量数据：中心药房一律 center；已验证药品医嘱按有无发药记录初始化 stage
return [
    'version' => '064',
    'description' => '双药房: 药房归属/预摆药/扣费流水/执行预留',
    'up' => function($m) {

        /* ==================== orders 扩展 ==================== */
        if ($m->hasTable('orders')) {
            $cols = [
                'pharmacy_type' => ['type' => 'VARCHAR', 'length' => '20', 'default' => 'center'], // center 中心药房 | outpatient 门诊药房
                'outpatient' => ['type' => 'INTEGER', 'default' => 0],        // 1=门诊药房药品(开方模式)
                'dispense_status' => ['type' => 'VARCHAR', 'length' => '20', 'default' => 'none'], // none|prepared(已预摆)|dispensed(已发药)|delivered(已送达)
                'charge_amount' => ['type' => 'REAL', 'default' => 0],        // 本医嘱摆药扣费金额
                'charged_at' => ['type' => 'TEXT'],                           // 扣费时间
                'plan_time' => ['type' => 'TEXT'],                            // 执行预留时间 (护士预摆药申请时填)
                'execute_batch_no' => ['type' => 'VARCHAR', 'length' => '50'],// 执行预留批次 (PDA/护士执行预留)
            ];
            foreach ($cols as $name => $def) {
                if (!$m->hasColumn('orders', $name)) $m->addColumn('orders', $name, $def);
            }
            $m->createIndex('orders', 'idx_orders_pharmacy_dispense', ['pharmacy_type', 'dispense_status', 'status']);
        }

        /* ==================== medications 扩展（对齐门诊拆零/规格口径） ==================== */
        if ($m->hasTable('medications')) {
            $cols = [
                'spec_pack_qty' => ['type' => 'INTEGER', 'default' => 1],     // 每包装最小单位数（如 24粒/盒）
                'spec_pack_unit' => ['type' => 'VARCHAR', 'length' => '20'],  // 最小单位名（粒/支/片/ml）
                'warn_qty' => ['type' => 'INTEGER', 'default' => 0],          // 警戒库存（最小单位整数）
                'allow_split' => ['type' => 'INTEGER', 'default' => 0],       // 是否允许拆零（0=仅整包装）
                'is_active' => ['type' => 'INTEGER', 'default' => 1],         // 停用标记（下架）
            ];
            foreach ($cols as $name => $def) {
                if (!$m->hasColumn('medications', $name)) $m->addColumn('medications', $name, $def);
            }
        }

        /* ==================== dispensing_records 扩展 stage 状态机 ==================== */
        if ($m->hasTable('dispensing_records')) {
            if (!$m->hasColumn('dispensing_records', 'stage')) {
                $m->addColumn('dispensing_records', 'stage', ['type' => 'VARCHAR', 'length' => '20', 'default' => 'dispensed']);
            }
            if (!$m->hasColumn('dispensing_records', 'amount')) {
                $m->addColumn('dispensing_records', 'amount', ['type' => 'REAL', 'default' => 0]);
            }
            // 存量发药记录视为 dispensed（stage 缺省已是 dispensed，防旧数据空值）
            $m->execute("UPDATE dispensing_records SET stage = 'dispensed' WHERE stage IS NULL OR stage = ''");
        }

        /* ==================== 扣费流水表 ==================== */
        if (!$m->hasTable('charge_records')) {
            $m->createTable('charge_records', [
                'id' => ['type' => 'INTEGER', 'primary' => true, 'auto_increment' => true],
                'patient_id' => ['type' => 'INTEGER', 'notnull' => true],
                'order_id' => ['type' => 'INTEGER'],
                'medication_id' => ['type' => 'INTEGER'],
                'quantity' => ['type' => 'INTEGER', 'default' => 0],   // 最小单位数量
                'amount' => ['type' => 'REAL', 'default' => 0],        // 本次扣费金额
                'deduct_type' => ['type' => 'VARCHAR', 'length' => '20', 'default' => 'dispense'], // dispense摆药扣费|refund退费回补
                'operator' => ['type' => 'INTEGER'],
                'created_at' => ['type' => 'TEXT'],
            ], ['if_not_exists' => true, 'foreign_keys' => [
                ['column' => 'patient_id', 'references' => 'patients', 'on' => 'id'],
                ['column' => 'order_id', 'references' => 'orders', 'on' => 'id'],
                ['column' => 'medication_id', 'references' => 'medications', 'on' => 'id'],
            ]]);
        }

        /* ==================== 门诊药房降级缓存表 ==================== */
        if (!$m->hasTable('opd_drug_cache')) {
            $m->createTable('opd_drug_cache', [
                'id' => ['type' => 'INTEGER', 'primary' => true, 'auto_increment' => true],
                'opd_id' => ['type' => 'INTEGER'],
                'name' => ['type' => 'VARCHAR', 'length' => '200'],
                'generic_name' => ['type' => 'VARCHAR', 'length' => '200'],
                'category' => ['type' => 'VARCHAR', 'length' => '50'],
                'vendor_short' => ['type' => 'VARCHAR', 'length' => '100'],
                'package_unit' => ['type' => 'VARCHAR', 'length' => '50'],
                'spec' => ['type' => 'VARCHAR', 'length' => '100'],
                'form' => ['type' => 'VARCHAR', 'length' => '50'],
                'single_dose' => ['type' => 'VARCHAR', 'length' => '100'],
                'frequency' => ['type' => 'VARCHAR', 'length' => '50'],
                'route' => ['type' => 'VARCHAR', 'length' => '50'],
                'price' => ['type' => 'REAL', 'default' => 0],
                'qty' => ['type' => 'INTEGER', 'default' => 0],
                'warn_qty' => ['type' => 'INTEGER', 'default' => 0],
                'spec_pack_qty' => ['type' => 'INTEGER', 'default' => 1],
                'spec_pack_unit' => ['type' => 'VARCHAR', 'length' => '20'],
                'allow_split' => ['type' => 'INTEGER', 'default' => 0],
                'is_active' => ['type' => 'INTEGER', 'default' => 1],
                'cached_at' => ['type' => 'TEXT'],
            ], ['if_not_exists' => true]);
            $m->createIndex('opd_drug_cache', 'idx_opd_drug_cache_opd', ['opd_id']);
        }

        /* ==================== 存量数据初始化 ==================== */
        if ($m->hasTable('orders')) {
            // 现有药品医嘱默认中心药房
            $m->execute("UPDATE orders SET pharmacy_type = 'center' WHERE pharmacy_type IS NULL OR pharmacy_type = ''");
            $m->execute("UPDATE orders SET dispense_status = 'none' WHERE dispense_status IS NULL OR dispense_status = ''");
            // 有发药记录的药品医嘱 → dispensed
            $m->execute("UPDATE orders SET dispense_status = 'dispensed'
                WHERE category = 'medication'
                  AND EXISTS (SELECT 1 FROM dispensing_records dr WHERE dr.order_id = orders.id AND dr.status = 'dispensed')");
        }
    }
];