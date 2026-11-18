<?php
// api/migrations/php/068_audits.php - 统一审核中心（参照门诊一体化）
// audits 表：待审核/已处理流（pending->approved/rejected/used；handled=关闭旧单）
return array(
    'version' => '068',
    'description' => '审核中心: audits 统一审核流表',
    'up' => function($m) {
        if ($m->hasTable('audits')) return;
        $m->createTable('audits', array(
            'id' => array('type' => 'INTEGER', 'primary' => true, 'auto_increment' => true),
            'type' => array('type' => 'VARCHAR', 'length' => '50', 'default' => ''),
            'ref_id' => array('type' => 'INTEGER', 'default' => 0),
            'title' => array('type' => 'VARCHAR', 'length' => '200', 'default' => ''),
            'content' => array('type' => 'TEXT', 'default' => ''),
            'status' => array('type' => 'VARCHAR', 'length' => '20', 'default' => 'pending'),
            'proposer' => array('type' => 'VARCHAR', 'length' => '100', 'default' => ''),
            'proposer_id' => array('type' => 'INTEGER', 'default' => 0),
            'created_at' => array('type' => 'TEXT'),
            'handled_by' => array('type' => 'VARCHAR', 'length' => '100', 'default' => ''),
            'handled_at' => array('type' => 'TEXT'),
            'note' => array('type' => 'TEXT', 'default' => ''),
            'data_json' => array('type' => 'TEXT', 'default' => ''),
            'creation_source' => array('type' => 'VARCHAR', 'length' => '50', 'default' => ''),
        ), array('if_not_exists' => true));
        $m->createIndex('audits', 'idx_audits_status_type', array('status', 'type'));
    }
);
