<?php
// api/modules/admin/drugs.php - 管理员药品管理（双药房）
// action=list：中心药房 medications CRUD 列表 + 门诊药房（仅读，实时查/降级缓存）
// action=save：中心药房新增/编辑药品（含库存/拆零/警戒）
// action=delete：停用中心药房药品
Auth::requireRole(['admin']);
$action = Validator::get('action', 'list');

if ($action === 'list') {
    $type = Validator::get('type', 'center');
    $kw = trim((string)Validator::get('kw', ''));
    $page = max(1, (int)Validator::get('page', 1) ?: 1);
    $per = max(1, min(100, (int)Validator::get('per', 20) ?: 20));

    if ($type === 'outpatient') {
        // 门诊药房：实时查+缓存（只读）
        $r = opd_pharmacy_drugs();
        $rows = [];
        foreach (($r['list'] ?? []) as $it) {
            $rows[] = [
                'id' => (int)($it['id'] ?? 0),
                'name' => (string)($it['name'] ?? ''),
                'category' => (string)($it['category'] ?? ''),
                'spec' => (string)($it['spec'] ?? ''),
                'unit' => (string)($it['package_unit'] ?? ''),
                'price' => (float)($it['price'] ?? 0),
                'stock' => (int)($it['qty'] ?? 0),
                'warn_qty' => (int)($it['warn_qty'] ?? 0),
                'spec_pack_qty' => (int)($it['spec_pack_qty'] ?? 1),
                'spec_pack_unit' => (string)($it['spec_pack_unit'] ?? ''),
                'allow_split' => (int)($it['allow_split'] ?? 0),
                'form' => (string)($it['form'] ?? ''),
                'single_dose' => (string)($it['single_dose'] ?? ''),
                'frequency' => (string)($it['frequency'] ?? ''),
                'route' => (string)($it['route'] ?? ''),
                'readonly' => true,
                'source' => $r['source'],
            ];
        }
    } else {
        $rows = [];
        foreach (DB::select("SELECT * FROM medications ORDER BY name") as $m) {
            $rows[] = [
                'id' => (int)$m['id'],
                'code' => (string)$m['code'],
                'name' => (string)$m['name'],
                'category' => (string)($m['drug_class'] ?? '西药'),
                'spec' => (string)($m['specification'] ?? ''),
                'unit' => (string)($m['unit'] ?? ''),
                'price' => (float)$m['price'],
                'stock' => (int)($m['stock_quantity'] ?? 0),
                'warn_qty' => (int)($m['warn_qty'] ?? 0),
                'spec_pack_qty' => (int)($m['spec_pack_qty'] ?? 1),
                'spec_pack_unit' => (string)($m['spec_pack_unit'] ?? ''),
                'allow_split' => (int)($m['allow_split'] ?? 0),
                'form' => (string)($m['dosage_form'] ?? ''),
                'single_dose' => (string)($m['default_dosage'] ?? ''),
                'frequency' => (string)($m['default_freq'] ?? ''),
                'route' => (string)($m['default_route'] ?? ''),
                'readonly' => false,
                'source' => 'center',
            ];
        }
    }

    // 关键字过滤
    if ($kw !== '') {
        $rows = array_values(array_filter($rows, function ($r) use ($kw) {
            return mb_stripos($r['name'], $kw) !== false
                || mb_stripos($r['spec'] ?? '', $kw) !== false
                || mb_stripos($r['category'] ?? '', $kw) !== false;
        }));
    }
    $total = count($rows);
    $rows = array_slice($rows, ($page - 1) * $per, $per);

    Response::success([
        'rows' => $rows, 'total' => $total, 'type' => $type,
        'source' => $type === 'outpatient' ? ($rows && isset($rows[0]['source']) ? $rows[0]['source'] : 'none') : 'center',
        'opd_enabled' => opd_settings()['opd_enabled'] === '1',
        'msg' => $type === 'outpatient' && isset($rows[0]['source']) && $rows[0]['source'] === 'cache' ? '门诊药房实时查询失败，当前为离线缓存（库存/价格可能过期）' : '',
    ]);
}

// 中心药房新增/编辑
if ($action === 'save') {
    $id = (int)Validator::get('id', 0);
    $name = trim((string)Validator::get('name', ''));
    $spec = trim((string)Validator::get('spec', ''));
    $unit = trim((string)Validator::get('unit', ''));
    $price = (float)Validator::get('price', 0);
    $stock = (int)Validator::get('stock', 0);
    $specPackQty = max(1, (int)Validator::get('spec_pack_qty', 1) ?: 1);
    $specPackUnit = trim((string)Validator::get('spec_pack_unit', ''));
    $allowSplit = (int)Validator::get('allow_split', 0) ? 1 : 0;
    $warnQty = (int)Validator::get('warn_qty', 0);
    $category = trim((string)Validator::get('category', '西药'));
    $form = trim((string)Validator::get('form', ''));
    $defaultDosage = trim((string)Validator::get('single_dose', ''));
    $defaultFreq = trim((string)Validator::get('frequency', ''));
    $defaultRoute = trim((string)Validator::get('route', ''));
    if ($name === '') Response::error('药品名称不能为空');
    if ($price < 0) Response::error('价格不能为负');

    $pdo = DB::getPDO();
    if ($id > 0) {
        // 编辑
        $exists = DB::selectOne("SELECT id FROM medications WHERE id = ?", [$id]);
        if (!$exists) Response::error('药品不存在');
        DB::execute("UPDATE medications SET name=?, specification=?, unit=?, price=?, stock_quantity=?,
                     spec_pack_qty=?, spec_pack_unit=?, allow_split=?, warn_qty=?, drug_class=?, dosage_form=?,
                     default_dosage=?, default_freq=?, default_route=?, is_active=1 WHERE id=?",
            [$name, $spec, $unit, $price, $stock, $specPackQty, $specPackUnit, $allowSplit, $warnQty, $category, $form,
             $defaultDosage, $defaultFreq, $defaultRoute, $id]);
        Response::success([], '药品已更新');
    } else {
        // 新增：自动生成编码
        $code = 'M' . date('YmdHis') . mt_rand(100, 999);
        DB::execute("INSERT INTO medications (code, name, specification, unit, price, stock_quantity,
                       spec_pack_qty, spec_pack_unit, allow_split, warn_qty, drug_class, dosage_form,
                       default_dosage, default_freq, default_route, is_active, pharmacy, created_at)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,1,'中心药房',?)",
            [$code, $name, $spec, $unit, $price, $stock, $specPackQty, $specPackUnit, $allowSplit, $warnQty,
             $category, $form, $defaultDosage, $defaultFreq, $defaultRoute, DateHelper::now()]);
        Response::success([], '药品已新增');
    }
}

// 停用/启用中心药房药品
if ($action === 'toggle') {
    $id = (int)Validator::get('id', 0);
    $on = (int)Validator::get('on', 0) ? 1 : 0;
    if (!$id) Response::error('参数错误');
    DB::execute("UPDATE medications SET is_active = ? WHERE id = ?", [$on, $id]);
    Response::success([], $on ? '已启用' : '已停用');
}
