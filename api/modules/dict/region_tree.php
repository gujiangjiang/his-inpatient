<?php
// api/modules/dict/region_tree.php - 省市县级联数据 (独立字典库; 首页/管理端共用)
Auth::requireRole(['admin', 'doctor', 'nurse', 'pharmacist', 'lab_tech']);
header('Cache-Control: no-cache');
Response::success(dict_region_tree());
