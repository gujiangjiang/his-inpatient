<?php
// api/migrations/php/015_ungroup_nonmedication.php - 非药品不设子医嘱: 拆分历史成组数据
return [
    'version' => '015',
    'description' => '拆分历史非药品成组医嘱为独立医嘱',
    'up' => function($m) {
        if (!$m->hasTable('orders')) return;
        $roots = DB::select("SELECT id FROM orders WHERE parent_id IS NULL AND is_group_main = 1");
        foreach ($roots as $r) {
            $hasMed = DB::selectOne("SELECT id FROM orders WHERE parent_id = ? AND category = 'medication' LIMIT 1", [$r['id']]);
            if ($hasMed) continue;
            // 非药品成组: 子医嘱转为独立医嘱, 主医嘱取消组标记
            DB::execute("UPDATE orders SET parent_id = NULL, is_group_main = 0, group_seq = 0 WHERE parent_id = ?", [$r['id']]);
            DB::execute("UPDATE orders SET is_group_main = 0 WHERE id = ?", [$r['id']]);
        }
    }
];
