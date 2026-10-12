// public/js/admin/integrations.js - 接口管理页交互 (门诊一体化 / PACS / 对接端点)
var _integrationList = [];
var _BIZ_LABELS = { lab: '检验', exam: '检查', prescription: '处方', treatment: '处置', patient: '患者' };
var _AUTH_LABELS = { none: '无', basic: 'Basic', token: 'Token', oauth2: 'OAuth2' };

async function admin_integrations_init() {
    document.getElementById('main-app').style.display = 'flex';
    document.getElementById('login-container').style.display = 'none';
    const sidebar = await fetch('/js/shared/sidebar_admin.php').then(r => r.text());
    document.getElementById('sidebar').innerHTML = sidebar;
    await admin_integrations_load_config();
    loadIntegrations();
}

function admin_integrations_switch(name) {
    ['opd', 'pacs', 'endpoints', 'audit', 'help'].forEach(function(k) {
        document.getElementById('itg-pane-' + k).classList.toggle('hidden', k !== name);
        document.getElementById('itg-tab-' + k).classList.toggle('active', k === name);
    });
    if (name === 'audit') admin_integrations_auditList();
}

async function admin_integrations_load_config() {
    const r = await AjaxLoader.api('admin/integration-config', { silent: true });
    const c = r.data;
    document.getElementById('itg-opd-enabled').checked = c.opd_enabled;
    document.getElementById('itg-opd-base').value = c.opd_base_url || '';
    document.getElementById('itg-opd-fhir-token').value = c.opd_fhir_token || '';
    document.getElementById('itg-opd-his-token').value = c.opd_his_token || '';
    document.getElementById('itg-pacs-enabled').checked = c.pacs_enabled;
    document.getElementById('itg-pacs-viewer').value = c.pacs_viewer_url || '';
    admin_integrations_status(c);
}

function admin_integrations_status(c) {
    const badge = (ok, onText, offText) => ok
        ? '<span class="badge badge-success">' + onText + '</span>'
        : '<span class="badge badge-default">' + offText + '</span>';
    const testHtml = c.last_test_at
        ? (c.last_test_ok === '1'
            ? '<span class="badge badge-success">通过</span> ' + Format.escape(c.last_test_at)
            : '<span class="badge badge-danger">未通过</span> ' + Format.escape(c.last_test_at))
        : '<span class="text-muted">尚未测试</span>';
    const rows = [
        ['门诊接口', badge(c.opd_enabled, '已启用', '未启用')],
        ['门诊服务地址', c.opd_base_url ? Format.escape(c.opd_base_url) : '<span class="text-muted">未配置</span>'],
        ['FHIR Token', badge(!!c.opd_fhir_token, '已配置', '未配置')],
        ['业务 Token', badge(!!c.opd_his_token, '已配置', '未配置')],
        ['PACS 阅片', badge(c.pacs_enabled, '已启用', '未启用')],
        ['阅片地址', c.pacs_viewer_url ? Format.escape(c.pacs_viewer_url) : '<span class="text-muted">未配置</span>'],
        ['最近测试', testHtml],
    ];
    const p = c.order_push || { pending: 0, failed: 0, success: 0 };
    if (p.failed > 0) {
        rows.push(['单据推送', '<span class="badge badge-danger">失败 ' + p.failed + ' 条</span> <span class="text-muted">可点「重推失败单据」</span>']);
    } else if (p.pending > 0) {
        rows.push(['单据推送', '<span class="badge badge-warning">待推送 ' + p.pending + ' 条</span>']);
    } else if (p.success > 0) {
        rows.push(['单据推送', '<span class="badge badge-success">已推送 ' + p.success + ' 条</span>']);
    }
    document.getElementById('itg-opd-status').innerHTML = rows.map(r =>
        '<div class="status-row"><span class="status-label">' + r[0] + '</span><span class="status-value">' + r[1] + '</span></div>'
    ).join('');
}

async function admin_integrations_save() {
    const data = {
        opd_enabled: document.getElementById('itg-opd-enabled').checked ? 1 : 0,
        opd_base_url: document.getElementById('itg-opd-base').value.trim(),
        opd_fhir_token: document.getElementById('itg-opd-fhir-token').value.trim(),
        opd_his_token: document.getElementById('itg-opd-his-token').value.trim(),
        pacs_enabled: document.getElementById('itg-pacs-enabled').checked ? 1 : 0,
        pacs_viewer_url: document.getElementById('itg-pacs-viewer').value.trim(),
    };
    const r = await AjaxLoader.api('admin/integration-config-save', { method: 'POST', data });
    if (r.status === 'success') {
        Dom.toast('配置已保存', 'success');
        admin_integrations_load_config();
    }
}

async function admin_integrations_test() {
    const r = await AjaxLoader.api('admin/integration-test', { silent: true });
    const items = (r.data && r.data.items) || [];
    document.getElementById('itg-test-result').innerHTML = items.map(it =>
        '<div class="tr-row"><span class="badge ' + (it.ok ? 'badge-success' : 'badge-danger') + '">' + (it.ok ? '通过' : '未通过') + '</span>' +
        '<span class="tr-name">' + Format.escape(it.name) + '</span><span class="tr-detail">' + Format.escape(it.detail) + '</span></div>'
    ).join('');
    Dom.toast(r.data && r.data.ok ? '连通性全部通过' : '存在未通过项，请检查配置', r.data && r.data.ok ? 'success' : 'error');
    admin_integrations_load_config();
}

async function admin_integrations_auditList() {
    const r = await AjaxLoader.api('admin/integration-audit-list', { silent: true });
    const rows = r.data || [];
    const tbody = document.getElementById('itg-audit-body');
    if (!rows.length) {
        tbody.innerHTML = '<tr><td colspan="4"><div class="empty-state">暂无变更记录</div></td></tr>';
        return;
    }
    tbody.innerHTML = rows.map(a =>
        '<tr><td>' + Format.escape(a.created_at) + '</td><td>' + Format.escape(a.actor_name) + '</td>' +
        '<td>' + Format.escape(a.detail) + '</td><td>' + Format.escape(a.ip) + '</td></tr>'
    ).join('');
}

function admin_integrations_auditClear() {
    Dom.modal('清空变更记录', '<p>确定清空全部配置变更记录？</p>', [
        { text: '清空', type: 'danger', onclick: async function() {
            await AjaxLoader.api('admin/integration-audit-clear', { method: 'POST' });
            Dom.toast('已清空', 'success');
            admin_integrations_auditList();
        } },
        { text: '取消', type: 'default' }
    ]);
}

function admin_integrations_push() {
    Dom.modal('推送字典确认', '<p>将把 HIS 药品目录、处置项目、检验/检查项目名推送到门诊一体化系统（门诊侧新增项需管理员审核后启用）。</p>', [
        { text: '推送', type: 'primary', onclick: async function() {
            const r = await AjaxLoader.api('admin/integration-push-catalog', { method: 'POST' });
            if (r.status === 'success') {
                const c = r.data.counts;
                Dom.toast('推送完成：药品 ' + c.drugs + ' · 处置 ' + c.disposal_items + ' · 检验 ' + c.lab_items + ' · 检查 ' + c.exam_items, 'success');
            }
        } },
        { text: '取消', type: 'default' }
    ]);
}

function admin_integrations_retryOrders() {
    Dom.modal('重推失败单据', '<p>将重推门诊推送队列中待推送 / 失败（重试未超限）的住院检验检查单，住院单一律视为已缴费。</p>', [
        { text: '重推', type: 'primary', onclick: async function() {
            const r = await AjaxLoader.api('admin/integration-retry-orders', { method: 'POST' });
            Dom.toast(r.message || '已处理', (r.data && r.data.failed > 0) ? 'error' : 'success');
            admin_integrations_load_config();
        } },
        { text: '取消', type: 'default' }
    ]);
}

/* ===== 对接端点登记 (标准端点 CRUD) ===== */
async function loadIntegrations() {
    const r = await AjaxLoader.api('admin/integration-list', { silent: true });
    _integrationList = r.data || [];
    const tbody = document.getElementById('integration-table-body');
    if (_integrationList.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7"><div class="empty-state">尚未登记对接端点</div></td></tr>';
        return;
    }
    tbody.innerHTML = _integrationList.map(e => `
        <tr>
            <td>${Format.escape(e.name)}</td>
            <td><span class="badge badge-info">${Format.escape(e.system_type)}</span></td>
            <td>${_BIZ_LABELS[e.biz_type] || Format.escape(e.biz_type || '-')}</td>
            <td class="cell-ellipsis" title="${Format.escape(e.base_url || '')}">${Format.escape(e.base_url || '-')}</td>
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
