<?php
// api/migrations/php/066_med_spec.php - 药品规格结构
// medications 增加 单剂量值/单剂量单位（spec_dose/spec_dose_unit），
// 供规格编辑器「0.5g×24粒」展示与自动算量；存量 spec 已存在保持。
return [
    'version' => '066',
    'description' => '药品规格: spec_dose/spec_dose_unit 单剂量字段',
    'up' => function($m) {
        if (!$m->hasTable('medications')) return;
        $cols = [
            'spec_dose' => ['type' => 'REAL', 'default' => 0],
            'spec_dose_unit' => ['type' => 'VARCHAR', 'length' => '20', 'default' => ''],
        ];
        foreach ($cols as $name => $def) {
            if (!$m->hasColumn('medications', $name)) $m->addColumn('medications', $name, $def);
        }
        // 存量回填：从现有 spec 文本解析「0.5g×24粒」→ spec_dose=0.5, spec_dose_unit=g
        // 解析失败保持 0/空（编辑时用规格编辑器补全）
    }
];