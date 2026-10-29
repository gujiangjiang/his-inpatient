<?php
// api/migrations/php/022_lab_exam_catalog.php - 检验/检查项目库 (虚拟数据, 预留接口对接门诊项目库) + 申请单关联医嘱
return [
    'version' => '022',
    'description' => '创建检验/检查项目库与检验套组模板, 申请单增加关联医嘱',
    'up' => function($m) {
        if (!$m->hasTable('lab_catalog')) {
            $m->createTable('lab_catalog', [
                'id' => ['type' => 'INTEGER', 'primary' => true, 'auto_increment' => true],
                'name' => ['type' => 'VARCHAR', 'length' => '200', 'notnull' => true],
                'specimen' => ['type' => 'VARCHAR', 'length' => '50'],      // 标本类别
                'dept' => ['type' => 'VARCHAR', 'length' => '100'],         // 执行科室
                'price' => ['type' => 'REAL', 'default' => 0],              // 参考价格
                'report_time' => ['type' => 'VARCHAR', 'length' => '50'],   // 报告时间
                'note' => ['type' => 'TEXT'],                               // 医嘱说明
                'category' => ['type' => 'VARCHAR', 'length' => '50'],
                'is_active' => ['type' => 'INTEGER', 'default' => 1],
                'sort_no' => ['type' => 'INTEGER', 'default' => 0],
                'created_at' => ['type' => 'TEXT'],
            ]);
            $rows = [
                ['血常规', '静脉血', '检验科', 25, '30分钟', '空腹采血更佳', '常规'],
                ['尿常规', '晨尿', '检验科', 15, '30分钟', '留取中段尿', '常规'],
                ['便常规', '粪便', '检验科', 12, '30分钟', '', '常规'],
                ['肝功能全套', '静脉血', '检验科', 120, '当日16:00', '空腹8-12小时', '生化'],
                ['肾功能', '静脉血', '检验科', 80, '当日16:00', '空腹更佳', '生化'],
                ['电解质', '静脉血', '检验科', 60, '当日16:00', '', '生化'],
                ['空腹血糖', '静脉血', '检验科', 10, '30分钟', '空腹8小时以上', '生化'],
                ['血脂全套', '静脉血', '检验科', 100, '当日16:00', '空腹12小时', '生化'],
                ['糖化血红蛋白', '静脉血', '检验科', 90, '当日', '无需空腹', '生化'],
                ['C反应蛋白(CRP)', '静脉血', '检验科', 40, '1小时', '', '免疫'],
                ['降钙素原(PCT)', '静脉血', '检验科', 160, '2小时', '', '免疫'],
                ['甲状腺功能五项', '静脉血', '检验科', 240, '次日', '空腹', '免疫'],
                ['心肌酶谱', '静脉血', '检验科', 130, '2小时', '胸痛患者尽快', '心脏标志物'],
                ['肌钙蛋白I', '静脉血', '检验科', 150, '1小时', '', '心脏标志物'],
                ['BNP/NT-proBNP', '静脉血', '检验科', 260, '2小时', '心衰评估', '心脏标志物'],
                ['淀粉酶', '静脉血', '检验科', 35, '2小时', '', '生化'],
                ['凝血四项', '静脉血', '检验科', 90, '2小时', '避免溶血', '凝血'],
                ['D-二聚体', '静脉血', '检验科', 120, '2小时', '', '凝血'],
                ['血型鉴定', '静脉血', '输血科', 20, '1小时', '', '输血'],
                ['输血前检查(四项)', '静脉血', '输血科', 180, '当日', 'HIV/乙肝/丙肝/梅毒', '输血'],
                ['血气分析', '动脉血', '检验科', 120, '15分钟', '即时送检', '急诊'],
                ['痰培养+药敏', '痰液', '检验科', 150, '3-5天', '晨起深部痰', '微生物'],
                ['尿培养+药敏', '尿液', '检验科', 140, '3-5天', '无菌留取中段尿', '微生物'],
                ['血培养', '静脉血', '检验科', 220, '5-7天', '发热时双侧双瓶', '微生物'],
            ];
            $i = 0;
            foreach ($rows as $r) {
                $m->execute(
                    "INSERT INTO lab_catalog (name, specimen, dept, price, report_time, note, category, is_active, sort_no, created_at) VALUES (?,?,?,?,?,?,?,1,?,?)",
                    [$r[0], $r[1], $r[2], $r[3], $r[4], $r[5], $r[6], $i++, DateHelper::now()]
                );
            }
        }
        if (!$m->hasTable('exam_catalog')) {
            $m->createTable('exam_catalog', [
                'id' => ['type' => 'INTEGER', 'primary' => true, 'auto_increment' => true],
                'name' => ['type' => 'VARCHAR', 'length' => '200', 'notnull' => true],
                'category' => ['type' => 'VARCHAR', 'length' => '50'],      // 类别: CT室/DR室/磁共振室/超声室...
                'sub_category' => ['type' => 'VARCHAR', 'length' => '100'], // 子类: 部位/类型
                'dept' => ['type' => 'VARCHAR', 'length' => '100'],         // 执行科室
                'price' => ['type' => 'REAL', 'default' => 0],
                'is_active' => ['type' => 'INTEGER', 'default' => 1],
                'sort_no' => ['type' => 'INTEGER', 'default' => 0],
                'created_at' => ['type' => 'TEXT'],
            ]);
            $rows = [
                ['头颅CT平扫', 'CT室', '头部', '放射影像科', 150],
                ['胸部CT平扫', 'CT室', '胸部', '放射影像科', 300],
                ['腹部CT平扫', 'CT室', '腹部', '放射影像科', 320],
                ['腰椎CT平扫', 'CT室', '脊柱', '放射影像科', 320],
                ['头颅CT增强', 'CT室', '头部', '放射影像科', 480],
                ['胸部CT增强', 'CT室', '胸部', '放射影像科', 620],
                ['胸部DR正位', 'DR室', '胸部', '放射影像科', 60],
                ['腰椎DR正侧位', 'DR室', '脊柱', '放射影像科', 90],
                ['四肢DR(单部位)', 'DR室', '四肢', '放射影像科', 55],
                ['腹部立位平片', 'DR室', '腹部', '放射影像科', 60],
                ['头颅MRI平扫', '磁共振室', '头部', '放射影像科', 480],
                ['颈椎MRI平扫', '磁共振室', '脊柱', '放射影像科', 520],
                ['腰椎MRI平扫', '磁共振室', '脊柱', '放射影像科', 520],
                ['上腹部MRI平扫', '磁共振室', '腹部', '放射影像科', 560],
                ['膝关节MRI平扫', '磁共振室', '关节', '放射影像科', 480],
                ['腹部超声', '超声室', '腹部', '超声医学科', 120],
                ['心脏彩超', '超声室', '心脏', '超声医学科', 260],
                ['甲状腺超声', '超声室', '浅表', '超声医学科', 100],
                ['颈动脉超声', '超声室', '血管', '超声医学科', 180],
                ['泌尿系超声', '超声室', '泌尿', '超声医学科', 120],
                ['妇科超声', '超声室', '妇科', '超声医学科', 130],
                ['常规心电图', '心电图室', '常规', '心功能室', 25],
                ['动态心电图(Holter)', '心电图室', '动态', '心功能室', 220],
                ['电子胃镜', '内镜室', '上消化道', '消化内镜室', 380],
                ['电子肠镜', '内镜室', '下消化道', '消化内镜室', 480],
            ];
            $i = 0;
            foreach ($rows as $r) {
                $m->execute(
                    "INSERT INTO exam_catalog (name, category, sub_category, dept, price, is_active, sort_no, created_at) VALUES (?,?,?,?,?,1,?,?)",
                    [$r[0], $r[1], $r[2], $r[3], $r[4], $i++, DateHelper::now()]
                );
            }
        }
        if (!$m->hasTable('lab_panels')) {
            $m->createTable('lab_panels', [
                'id' => ['type' => 'INTEGER', 'primary' => true, 'auto_increment' => true],
                'name' => ['type' => 'VARCHAR', 'length' => '200', 'notnull' => true],
                'items_json' => ['type' => 'TEXT'],       // 项目 ID 列表
                'created_by' => ['type' => 'INTEGER'],
                'dept_id' => ['type' => 'INTEGER'],
                'created_at' => ['type' => 'TEXT'],
            ]);
        }
        if ($m->hasTable('lab_reports') && !$m->hasColumn('lab_reports', 'order_ids')) {
            $m->addColumn('lab_reports', 'order_ids', ['type' => 'TEXT']);
        }
        if ($m->hasTable('exam_reports') && !$m->hasColumn('exam_reports', 'order_ids')) {
            $m->addColumn('exam_reports', 'order_ids', ['type' => 'TEXT']);
        }
    }
];
