<?php header("Content-Type: text/html; charset=utf-8"); ?>
<div class="flex-between mb-20">
    <h2>对接管理
        <span style="font-size:12px;color:#909399;font-weight:400">门诊一体化预留 · FHIR / HL7 / HIS / DICOM</span>
    </h2>
    <button class="btn btn-primary" onclick="admin_integrations_new()">新增端点</button>
</div>

<div class="card">
    <table class="table">
        <thead><tr><th>名称</th><th>标准</th><th>业务</th><th>地址</th><th>鉴权</th><th>状态</th><th>操作</th></tr></thead>
        <tbody id="integration-table-body"></tbody>
    </table>
    <p style="margin-top:12px;font-size:12px;color:#909399">
        说明：本页为与门诊一体化系统对接的预留配置；后续在此按标准实现检验/检查/处方/处置等业务数据交换（adapters 待实现）。
    </p>
</div>

<script>
var _integrationList = [];
var _BIZ_LABELS = { lab: '检验', exam: '检查', prescription: '处方', treatment: '处置', patient: '患者' };
var _AUTH_LABELS = { none: '无', basic: 'Basic', token: 'Token', oauth2: 'OAuth2' };

async function admin_integrations_init() {
    document.getElementById('main-app').style.display = 'flex';
    document.getElementById('login-container').style.display = 'none';
    const sidebar = await fetch('/js/shared/sidebar_admin.php').then(r => r.text());
    document.getElementById('sidebar').innerHTML = sidebar;
    loadIntegrations();
}

async function loadIntegrations() {
    const r = await AjaxLoader.api('admin/integration-list', { silent: true });
    _integrationList = r.data || [];
    const tbody = document.getElementById('integration-table-body');
    if (_integrationList.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;color:#909399">尚未配置对接端点</td></tr>';
        return;
    }
    tbody.innerHTML = _integrationList.map(e => `
        <tr>
            <td>${Format.escape(e.name)}</td>
            <td><span class="badge badge-info">${Format.escape(e.system_type)}</span></td>
            <td>${_BIZ_LABELS[e.biz_type] || Format.escape(e.biz_type || '-')}</td>
            <td style="max-width:260px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${Format.escape(e.base_url || '-')}</td>
            <td>${_AUTH_LABELS[e.auth_type] || '-'}</td>
            <td>${e.enabled == 1 ? '<span class="badge badge-success">启用</span>' : '<span class="badge badge-default">停用</span>'}</td>
            <td>
                <button class="btn btn-small" onclick="admin_integrations_edit(${e.id})">编辑</button>
                <button class="btn btn-small" onclick="admin_integrations_toggle(${e.id})">${e.enabled == 1 ? '停用' : '启用'}</button>
                <button class="btn btn-small btn-danger" onclick="admin_integrations_delete(${e.id})">删除</button>
            </td>
        </tr>`).join('');
}

function admin_integrations_new() { showIntegrationForm({}); }

function admin_integrations_edit(id) {
    const item = _integrationList.find(e => e.id == id);
    if (item) showIntegrationForm(item);
}

async function admin_integrations_toggle(id) {
    const item = _integrationList.find(e => e.id == id);
    if (!item) return;
    await AjaxLoader.api('admin/integration-save', { method: 'POST', data: {
        id: item.id, name: item.name, system_type: item.system_type, biz_type: item.biz_type,
        base_url: item.base_url, auth_type: item.auth_type, auth_config: item.auth_config || '',
        enabled: item.enabled == 1 ? 0 : 1, remark: item.remark || ''
    }});
    Dom.toast(item.enabled == 1 ? '已停用' : '已启用', 'success');
    loadIntegrations();
}

function admin_integrations_delete(id) {
    Dom.modal('确认删除', '<p>删除该对接端点？</p>', [
        { text: '删除', type: 'danger', onclick: async function() {
            await AjaxLoader.api('admin/integration-delete', { method: 'POST', data: { id } });
            Dom.toast('已删除', 'success');
            loadIntegrations();
        } },
        { text: '取消', type: 'default' }
    ]);
}

function showIntegrationForm(item) {
    const sysOpts = ['FHIR', 'HL7', 'HIS', 'DICOM'].map(v => `<option value="${v}" ${item.system_type === v ? 'selected' : ''}>${v}</option>`).join('');
    const bizOpts = Object.keys(_BIZ_LABELS).map(v => `<option value="${v}" ${item.biz_type === v ? 'selected' : ''}>${_BIZ_LABELS[v]}</option>`).join('');
    const authOpts = Object.keys(_AUTH_LABELS).map(v => `<option value="${v}" ${item.auth_type === v ? 'selected' : ''}>${_AUTH_LABELS[v]}</option>`).join('');
    const content = `
        <form id="integration-form" style="max-height:70vh;overflow-y:auto">
            ${Dom.formField({ name: 'name', label: '端点名称', type: 'text', required: true }, item.name || '')}
            <div class="form-group"><label class="form-label">接口标准</label><select name="system_type" class="form-control">${sysOpts}</select></div>
            <div class="form-group"><label class="form-label">业务类型</label><select name="biz_type" class="form-control">${bizOpts}</select></div>
            ${Dom.formField({ name: 'base_url', label: '服务地址', type: 'text' }, item.base_url || '')}
            <div class="form-group"><label class="form-label">鉴权方式</label><select name="auth_type" class="form-control">${authOpts}</select></div>
            ${Dom.formField({ name: 'auth_config', label: '鉴权配置 (JSON)', type: 'textarea', rows: 3 }, item.auth_config || '')}
            ${Dom.formField({ name: 'remark', label: '备注', type: 'text' }, item.remark || '')}
            <div class="form-group"><label class="form-label"><input type="checkbox" name="enabled" ${item.enabled == 0 ? '' : 'checked'}> 启用</label></div>
            <input type="hidden" name="id" value="${item.id || ''}">
        </form>`;
    Dom.modal('对接端点', content, [
        { text: '保存', type: 'primary', onclick: async function() {
            const data = {};
            new FormData(document.getElementById('integration-form')).forEach((v, k) => data[k] = v);
            data.enabled = document.querySelector('[name="enabled"]').checked ? 1 : 0;
            const r = await AjaxLoader.api('admin/integration-save', { method: 'POST', data });
            if (r.status === 'success') {
                Dom.toast('保存成功', 'success');
                loadIntegrations();
            }
        } },
        { text: '取消', type: 'default' }
    ]);
}
</script>
