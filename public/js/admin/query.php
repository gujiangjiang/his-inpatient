<?php header("Content-Type: text/html; charset=utf-8"); ?>
<div class="page-head">
    <h3 class="page-title">数据查询</h3>
    <p class="page-desc">诊断/检验/检查/药品（外部接口优先、本地自动回退）与基础字段数据（省市县邮编、职业大典、院级字典）</p>
</div>

<div class="card">
    <div class="tabs" id="dq-tabs">
        <button type="button" class="tab active" data-kind="diagnosis">诊断</button>
        <button type="button" class="tab" data-kind="lab">检验</button>
        <button type="button" class="tab" data-kind="exam">检查</button>
        <button type="button" class="tab" data-kind="drug">药品</button>
        <button type="button" class="tab" data-kind="region">省市县邮编</button>
        <button type="button" class="tab" data-kind="occupation">职业大典</button>
        <button type="button" class="tab" data-kind="dict">院级字典</button>
    </div>
    <div class="dq-bar">
        <input class="form-control" id="dq-kw" placeholder="输入关键字后回车查询（支持编码/名称/拼音首字母）" style="max-width:380px">
        <select class="form-control hidden" id="dq-level" style="width:130px">
            <option value="0">全部层级</option>
            <option value="1">大类</option>
            <option value="2">中类</option>
            <option value="3">小类</option>
            <option value="4">职业（细类）</option>
        </select>
        <select class="form-control hidden" id="dq-dict" style="width:220px"></select>
        <button class="btn btn-primary" onclick="admin_dq_search()">查询</button>
        <span class="text-muted fs-12" id="dq-source"></span>
    </div>
    <div id="dq-result"></div>
</div>

<script>
var _dqKind = 'diagnosis';
var _dqDictList = [];

async function admin_query_init() {
    document.getElementById('main-app').style.display = 'flex';
    document.getElementById('login-container').style.display = 'none';
    const sidebar = await fetch('/js/shared/sidebar_admin.php').then(r => r.text());
    document.getElementById('sidebar').innerHTML = sidebar;
    document.querySelectorAll('#dq-tabs .tab').forEach(function (t) {
        t.addEventListener('click', function () { admin_dq_tab(t.dataset.kind); });
    });
    document.getElementById('dq-kw').addEventListener('keydown', function (e) {
        if (e.key === 'Enter') { e.preventDefault(); admin_dq_search(); }
    });
    const r = await AjaxLoader.api('admin/dict-list', { silent: true });
    _dqDictList = r.data || [];
    document.getElementById('dq-dict').innerHTML = _dqDictList.map(function (d) {
        return '<option value="' + Format.escape(d.dkey) + '">' + Format.escape(d.name) + '（' + d.items.length + '）</option>';
    }).join('');
    admin_dq_search();
}

function admin_dq_tab(kind) {
    _dqKind = kind;
    document.querySelectorAll('#dq-tabs .tab').forEach(function (t) { t.classList.toggle('active', t.dataset.kind === kind); });
    document.getElementById('dq-level').classList.toggle('hidden', kind !== 'occupation');
    document.getElementById('dq-dict').classList.toggle('hidden', kind !== 'dict');
    document.getElementById('dq-result').innerHTML = '';
    document.getElementById('dq-source').textContent = '';
    admin_dq_search();
}

async function admin_dq_search() {
    const kw = document.getElementById('dq-kw').value.trim();
    let url = 'admin/query?kind=' + _dqKind + '&limit=80&kw=' + encodeURIComponent(kw);
    if (_dqKind === 'occupation') url += '&level=' + document.getElementById('dq-level').value;
    if (_dqKind === 'dict') url += '&key=' + encodeURIComponent(document.getElementById('dq-dict').value);
    const r = await AjaxLoader.api(url, { silent: true });
    const d = r.data || {};
    document.getElementById('dq-source').textContent = d.source ? ('数据来源：' + d.source) : '';
    const box = document.getElementById('dq-result');
    const rows = d.rows || [];
    if (!rows.length) { box.innerHTML = '<div class="empty-state">未查询到数据</div>'; return; }
    if (_dqKind === 'dict') {
        /* 按 key 查询: 字符串数组 (选项列表) */
        box.innerHTML = '<div class="emr-calc-vars" style="max-height:none">' + rows.map(function (x) {
            return '<span class="emr-calc-var">' + Format.escape(typeof x === 'string' ? x : (x.name || '')) + '</span>';
        }).join('') + '</div>';
        return;
    }
    if (_dqKind === 'diagnosis') {
        box.innerHTML = '<table class="table"><thead><tr><th style="width:130px">编码</th><th>名称</th></tr></thead><tbody>'
            + rows.map(function (x) { return '<tr><td>' + Format.escape(x.code) + '</td><td>' + Format.escape(x.name) + '</td></tr>'; }).join('')
            + '</tbody></table>';
        return;
    }
    if (_dqKind === 'region') {
        box.innerHTML = '<table class="table"><thead><tr><th>省级</th><th>市级</th><th>区县</th><th style="width:110px">邮编</th><th style="width:120px">区划代码</th></tr></thead><tbody>'
            + rows.map(function (x) {
                return '<tr><td>' + Format.escape(x.province) + '</td><td>' + Format.escape(x.city) + '</td><td>' + Format.escape(x.county)
                    + '</td><td><b>' + Format.escape(x.zip || '') + '</b></td><td>' + Format.escape(x.code || '') + '</td></tr>';
            }).join('') + '</tbody></table>';
        return;
    }
    if (_dqKind === 'occupation') {
        const st = d.stats || {};
        box.innerHTML = '<div class="text-muted fs-12" style="margin:6px 0">大典统计：大类 ' + (st[1] || 0) + ' · 中类 ' + (st[2] || 0) + ' · 小类 ' + (st[3] || 0) + ' · 职业细类 ' + (st[4] || 0) + '</div>'
            + '<table class="table"><thead><tr><th style="width:120px">编码</th><th style="width:60px">层级</th><th>名称</th><th>分类路径</th></tr></thead><tbody>'
            + rows.map(function (x) {
                const lv = { 1: '大类', 2: '中类', 3: '小类', 4: '细类' }[x.level] || x.level;
                return '<tr><td>' + Format.escape(x.code) + '</td><td>' + lv + '</td><td>' + Format.escape(x.name) + '</td><td class="fs-12 text-muted">' + Format.escape(x.path || '') + '</td></tr>';
            }).join('') + '</tbody></table>';
        return;
    }
    /* lab / exam / drug */
    box.innerHTML = '<table class="table"><thead><tr><th>名称</th><th>规格</th><th style="width:120px">价格</th></tr></thead><tbody>'
        + rows.map(function (x) {
            return '<tr><td>' + Format.escape(x.name) + '</td><td>' + Format.escape(x.spec || '') + '</td><td>' + Format.escape(String(x.price || '')) + '</td></tr>';
        }).join('') + '</tbody></table>';
}
</script>
