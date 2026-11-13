<?php
// api/migrations/php/050_emr_publish_review.php - 模板/内容 发布与审核
// 新模型: 默认 personal(仅创建人可见) → 发布(提交审核, 可选 科室/全院) → 管理员审核
//   approved/department → 本科室可见; approved/all → 全院可见; rejected → 退回(附原因)
// 回填存量: 现有模板/内容按 department_id 视为已审核发布 (null→全院, 非空→该科室)
return [
    'version' => '050',
    'description' => '模板/内容增加发布状态与审核字段 (pub_status/pub_scope/审核人/驳回原因)',
    'up' => function ($m) {
        $tables = ['emr_templates' => 'emr_templates', 'emr_contents' => 'emr_contents'];
        foreach ($tables as $t) {
            if (!$m->hasTable($t)) continue;
            if (!$m->hasColumn($t, 'pub_status')) {
                $m->addColumn($t, 'pub_status', ['type' => 'VARCHAR', 'length' => 20, 'default' => 'personal', 'notnull' => true]);
            }
            if (!$m->hasColumn($t, 'pub_scope')) {
                $m->addColumn($t, 'pub_scope', ['type' => 'VARCHAR', 'length' => 20]);
            }
            if (!$m->hasColumn($t, 'pub_audited_by')) $m->addColumn($t, 'pub_audited_by', ['type' => 'INTEGER']);
            if (!$m->hasColumn($t, 'pub_audited_at')) $m->addColumn($t, 'pub_audited_at', ['type' => 'TEXT']);
            if (!$m->hasColumn($t, 'pub_reject_reason')) $m->addColumn($t, 'pub_reject_reason', ['type' => 'TEXT']);
            /* 回填: 存量视为已审核发布 */
            DB::execute(
                "UPDATE $t SET pub_status = 'approved', pub_scope = CASE WHEN department_id IS NULL THEN 'all' ELSE 'department' END"
            );
        }
    }
];