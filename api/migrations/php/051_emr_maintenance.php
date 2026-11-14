<?php
// 051_emr_maintenance.php - 病历维护申请 (签名病历 编辑/删除 的申请与审核解锁)
// 流程: 医生对已签名病历发起「病历维护」(编辑/删除, 附原因) → 管理员审核 →
//   approve: edit → 文书退回草稿可编辑; delete → 删除文书 (含历史版本)
//   reject: 附原因退回
return [
    'version' => '051',
    'description' => '病历维护申请 (签名病历编辑/删除解锁, 管理员审核)',
    'up' => function ($m) {
        if (!$m->hasTable('emr_maintenance_requests')) {
            $m->createTable('emr_maintenance_requests', [
                'id' => ['type' => 'INTEGER', 'primary' => true, 'auto_increment' => true],
                'doc_id' => ['type' => 'INTEGER', 'notnull' => true],
                'action' => ['type' => 'VARCHAR', 'length' => 10, 'notnull' => true], /* edit | delete */
                'reason' => ['type' => 'TEXT'],
                'status' => ['type' => 'VARCHAR', 'length' => 20, 'notnull' => true, 'default' => 'pending'],
                'requested_by' => ['type' => 'INTEGER'],
                'requested_at' => ['type' => 'TEXT'],
                'reviewed_by' => ['type' => 'INTEGER'],
                'reviewed_at' => ['type' => 'TEXT'],
                'reject_reason' => ['type' => 'TEXT'],
            ], ['if_not_exists' => true]);
        }
        $m->createIndex('emr_maintenance_requests', 'idx_emr_maint_doc', ['doc_id']);
        $m->createIndex('emr_maintenance_requests', 'idx_emr_maint_status', ['status']);
    }
];
