<?php
// api/migrations/php/016_integration.php - 外部系统对接端点 (门诊一体化预留)
return [
    'version' => '016',
    'description' => '创建外部系统对接端点表 integration_endpoints',
    'up' => function($m) {
        if ($m->hasTable('integration_endpoints')) return;
        $m->createTable('integration_endpoints', [
            'id' => ['type' => 'INTEGER', 'primary' => true, 'auto_increment' => true],
            'name' => ['type' => 'VARCHAR', 'length' => '200', 'notnull' => true],
            'system_type' => ['type' => 'VARCHAR', 'length' => '20', 'default' => 'FHIR'], // FHIR|HL7|HIS|DICOM
            'biz_type' => ['type' => 'VARCHAR', 'length' => '50'],                      // lab|exam|prescription|treatment|patient
            'base_url' => ['type' => 'VARCHAR', 'length' => '500'],
            'auth_type' => ['type' => 'VARCHAR', 'length' => '20', 'default' => 'none'], // none|basic|token|oauth2
            'auth_config' => ['type' => 'TEXT'],                                        // JSON: 账号/密钥/Token 等
            'enabled' => ['type' => 'INTEGER', 'default' => 1],
            'remark' => ['type' => 'TEXT'],
            'created_at' => ['type' => 'TEXT'],
            'updated_at' => ['type' => 'TEXT'],
        ], ['if_not_exists' => true]);
    }
];
