<?php
// api/migrations/php/070_drop_api_configs.php - 移除废弃的旧版「接口配置」通道
// 背景：api_configs（旧版通道：api-config-list/save）已被「接口管理」（integrations）
// 完全替代，前端页面/路由已移除，本迁移清理历史遗留表（生产数据为空）。
return [
    'version' => '070',
    'description' => '移除废弃 api_configs 表（旧版接口配置通道）',
    'up' => function($m) {
        if ($m->hasTable('api_configs')) {
            $m->execute('DROP TABLE api_configs');
        }
    }
];