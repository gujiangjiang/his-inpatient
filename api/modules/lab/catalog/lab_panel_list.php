<?php
// api/modules/lab/lab_panel_list.php - 常用检验套组模板列表 (本人)
Auth::requireRole(['admin', 'doctor']);
$user = Auth::user();
$rows = DB::select("SELECT * FROM lab_panels WHERE created_by = ? ORDER BY id DESC", [$user['id']]);
foreach ($rows as &$r) {
    $r['items'] = json_decode((string)$r['items_json'], true) ?: [];
}
Response::success($rows);
