<?php
// api/modules/admin/dict/dict_list.php - 全院统一字典列表 (管理页)
Auth::requireRole(['admin']);
Response::success(emr_dict_meta());
