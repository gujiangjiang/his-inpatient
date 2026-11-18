<?php header("Content-Type: text/html; charset=utf-8"); ?>
<div class="page-head">
    <h3 class="page-title">缓存与性能</h3>
    <p class="page-desc">会话缓存 · 品牌缓存 · 字典缓存状态与清理（参照门诊一体化）</p>
</div>

<div class="card">
    <div class="card-header">缓存状态</div>
    <div class="status-list" id="cachecenter-status"></div>
</div>

<div class="card">
    <div class="card-header">清理缓存</div>
    <div class="flex gap-8">
        <button class="btn btn-small" onclick="admin_cachecenter_flush('session')">清理会话缓存</button>
        <button class="btn btn-small" onclick="admin_cachecenter_flush('brand')">刷新品牌缓存</button>
        <button class="btn btn-small btn-danger" onclick="admin_cachecenter_flush('all')">清理全部</button>
    </div>
    <p class="hint">清理会话缓存会使所有登录会话失效（需重新登录）；品牌缓存刷新让新 Logo 立即可见。</p>
</div>

<script>
async function admin_cachecenter_init() {
    document.getElementById('main-app').style.display = 'flex';
    document.getElementById('login-container').style.display = 'none';
    const sidebar = await fetch('/js/shared/sidebar_admin.php').then(r => r.text());
    document.getElementById('sidebar').innerHTML = sidebar;
    admin_cachecenter_load();
}

async function admin_cachecenter_load() {
    const r = await AjaxLoader.api('admin/cache-center?action=status', { silent: true });
    const d = r.data || {};
    const box = document.getElementById('cachecenter-status');
    box.innerHTML = [
        ['会话目录', d.session_dir || '—'],
        ['字典库', d.dict_db || '—'],
        ['品牌版本', d.brand_revision || '0'],
        ['PHP 版本', d.php_version || '—'],
        ['运行模式', d.sapi || '—']
    ].map(function (row) {
        return '<div class="status-row"><span class="status-label">' + row[0] + '</span><span class="status-value">' + Format.escape(row[1]) + '</span></div>';
    }).join('');
}

async function admin_cachecenter_flush(target) {
    const label = target === 'session' ? '清理会话缓存' : (target === 'brand' ? '刷新品牌缓存' : '清理全部缓存');
    Dom.modal(label, target === 'session' ? '<p>清理会话缓存将使<b>所有登录会话失效</b>（包括你），需重新登录。确定吗？</p>' : '<p>确定执行 ' + label + '？</p>', [
        { text: '执行', type: 'danger', onclick: async function () {
            const r = await AjaxLoader.api('admin/cache-center', { method: 'POST', data: { action: 'flush', target: target } });
            if (r.status === 'success') { Dom.toast(r.message || '已清理', 'success'); admin_cachecenter_load(); }
            else Dom.toast(r.message || '清理失败', 'error');
        } },
        { text: '取消', type: 'default' }
    ]);
}
</script>