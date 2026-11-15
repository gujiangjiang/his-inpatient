<?php
// public/js/core/emr_dicts_js.php - 全院统一字典 → 前端注入 (数据源: api/core/emr_dicts.php)
// 病历下拉字段以 slot.dict 引用字典键, 选项列表全院统一、模板不内嵌
header('Content-Type: application/javascript; charset=utf-8');
require_once __DIR__ . '/../../../api/core/emr_dicts.php';
echo 'window.EmrDicts = ' . json_encode(emr_dicts(), JSON_UNESCAPED_UNICODE) . ';' . "\n";
echo "window.EmrDictsOf = function (key) { return (window.EmrDicts && window.EmrDicts[key]) ? window.EmrDicts[key].slice() : []; };\n";
