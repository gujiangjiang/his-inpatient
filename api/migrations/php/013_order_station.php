<?php
// api/migrations/php/013_order_station.php - 医嘱工作站: 长期/临时/用法/成组/组套
return [
    'version' => '013',
    'description' => '医嘱结构化扩展 (长期临时/用法/成组排序) + 药品目录扩展 + 医嘱组套表',
    'up' => function($m) {
        // === orders 扩展 ===
        if ($m->hasTable('orders')) {
            $columns = [
                'order_period' => ['type' => 'VARCHAR', 'length' => '10', 'default' => 'long'], // long 长期 | temp 临时
                'order_no' => ['type' => 'VARCHAR', 'length' => '50'],                          // 医嘱流水号
                'drug_id' => ['type' => 'INTEGER'],                                             // 关联药品目录
                'dosage_unit' => ['type' => 'VARCHAR', 'length' => '20'],                       // 剂量单位: 袋|支|片|ml
                'usage_name' => ['type' => 'VARCHAR', 'length' => '50'],                        // 用法/给药途径: 静脉输液|口服
                'usage_rate' => ['type' => 'VARCHAR', 'length' => '50'],                        // 滴速: 60滴/分
                'freq_code' => ['type' => 'VARCHAR', 'length' => '20'],                         // 频次代码: qd|bid|tid|q2h
                'freq_detail' => ['type' => 'VARCHAR', 'length' => '50'],                       // 频次时间: 07-16
                'group_seq' => ['type' => 'INTEGER', 'default' => 0],                           // 组内顺序
                'sort_no' => ['type' => 'INTEGER', 'default' => 0],                             // 展示排序
                'billing_flags' => ['type' => 'VARCHAR', 'length' => '100'],                    // 标签: 自费,径外,不计价,预防
                'stop_date' => ['type' => 'TEXT'],                                              // 停止时间
                'stopped_by' => ['type' => 'INTEGER'],                                          // 停止人
            ];
            foreach ($columns as $name => $def) {
                if (!$m->hasColumn('orders', $name)) {
                    $m->addColumn('orders', $name, $def);
                }
            }
            $m->createIndex('orders', 'idx_orders_period_status', ['patient_id', 'order_period', 'status']);
        }

        // === medications 扩展 ===
        if ($m->hasTable('medications')) {
            $columns = [
                'dosage_form' => ['type' => 'VARCHAR', 'length' => '50'],       // 剂型: 注射液|粉针|片剂|胶囊
                'manufacturer' => ['type' => 'VARCHAR', 'length' => '200'],     // 生产厂家
                'pharmacy' => ['type' => 'VARCHAR', 'length' => '100'],         // 发药药房
                'drug_class' => ['type' => 'VARCHAR', 'length' => '20'],        // 基|国|省|谈
                'pinyin_code' => ['type' => 'VARCHAR', 'length' => '50'],       // 拼音码 (lsmz...)
                'default_freq' => ['type' => 'VARCHAR', 'length' => '20'],      // 默认频次
                'default_route' => ['type' => 'VARCHAR', 'length' => '50'],     // 默认用法
                'default_dosage' => ['type' => 'VARCHAR', 'length' => '50'],    // 默认单次剂量
                'is_antibiotic' => ['type' => 'INTEGER', 'default' => 0],       // 抗菌药物
            ];
            foreach ($columns as $name => $def) {
                if (!$m->hasColumn('medications', $name)) {
                    $m->addColumn('medications', $name, $def);
                }
            }
        }

        // === 医嘱组套 (常用医嘱/路径) ===
        if (!$m->hasTable('order_sets')) {
            $m->createTable('order_sets', [
                'id' => ['type' => 'INTEGER', 'primary' => true, 'auto_increment' => true],
                'name' => ['type' => 'VARCHAR', 'length' => '200', 'notnull' => true],
                'category' => ['type' => 'VARCHAR', 'length' => '50', 'default' => 'medication'], // medication|nonmedication
                'department_id' => ['type' => 'INTEGER'],
                'is_active' => ['type' => 'INTEGER', 'default' => 1],
                'created_by' => ['type' => 'INTEGER'],
                'created_at' => ['type' => 'TEXT'],
            ], ['if_not_exists' => true, 'foreign_keys' => [
                ['column' => 'department_id', 'references' => 'departments', 'on' => 'id'],
                ['column' => 'created_by', 'references' => 'users', 'on' => 'id']
            ]]);
        }

        if (!$m->hasTable('order_set_items')) {
            $m->createTable('order_set_items', [
                'id' => ['type' => 'INTEGER', 'primary' => true, 'auto_increment' => true],
                'set_id' => ['type' => 'INTEGER', 'notnull' => true],
                'category' => ['type' => 'VARCHAR', 'length' => '50', 'default' => 'medication'],
                'drug_id' => ['type' => 'INTEGER'],
                'content' => ['type' => 'TEXT'],
                'dosage' => ['type' => 'VARCHAR', 'length' => '100'],
                'dosage_unit' => ['type' => 'VARCHAR', 'length' => '20'],
                'usage_name' => ['type' => 'VARCHAR', 'length' => '50'],
                'usage_rate' => ['type' => 'VARCHAR', 'length' => '50'],
                'freq_code' => ['type' => 'VARCHAR', 'length' => '20'],
                'freq_detail' => ['type' => 'VARCHAR', 'length' => '50'],
                'order_period' => ['type' => 'VARCHAR', 'length' => '10', 'default' => 'long'],
                'group_seq' => ['type' => 'INTEGER', 'default' => 0],
                'sort_no' => ['type' => 'INTEGER', 'default' => 0],
                'notes' => ['type' => 'TEXT'],
            ], ['if_not_exists' => true, 'foreign_keys' => [
                ['column' => 'set_id', 'references' => 'order_sets', 'on' => 'id']
            ]]);
        }
    }
];
