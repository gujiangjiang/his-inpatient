<?php header("Content-Type: text/html; charset=utf-8"); require_once __DIR__ . '/../../icons/icon.php'; ?>
<div class="page-head flex-between">
    <div>
        <h3 class="page-title">常规护理文书</h3>
        <p class="page-desc">入院评估、护理计划、健康宣教、转科记录等护理文书管理</p>
    </div>
    <button class="btn btn-primary" onclick="NurseDocPage.openAdd()">新建文书</button>
</div>
<div class="card">
    <div class="flex-between mb-10">
        <select id="ndoc-patient" class="form-control" style="width:240px;max-width:100%" onchange="NurseDocPage.load(this.value)">
            <option value="">选择患者</option>
        </select>
        <button class="btn" onclick="NurseDocPage.reload()">刷新</button>
    </div>
    <div id="ndoc-box" class="empty-state">请选择患者查看护理文书</div>
</div>

<script>
var NurseDocPage = {
    patientId: null,
    TYPE: { admission_assessment: '入院评估', nursing_plan: '护理计划', health_edu: '健康宣教', transfer_record: '转科记录', other: '其他' },

    async init() {
        await App.mountShell('nurse');
        const r = await AjaxLoader.api('nurse/patient-list', { silent: true });
        const sel = document.getElementById('ndoc-patient');
        (r.data || []).forEach(p => {
            const opt = document.createElement('option');
            opt.value = p.id; opt.textContent = (p.bed_no || '') + ' 床 ' + p.name;
            sel.appendChild(opt);
        });
    },

    load(pid) {
        this.patientId = pid;
        if (!pid) { document.getElementById('ndoc-box').innerHTML = '<div class="empty-state">请选择患者查看护理文书</div>'; return; }
        const box = document.getElementById('ndoc-box');
        box.innerHTML = '<div class="empty-state">加载中…</div>';
        AjaxLoader.api('nurse/nursing-docs?action=list&patient_id=' + pid, { silent: true }).then(r => {
            const rows = r.data || [];
            if (!rows.length) { box.innerHTML = '<div class="empty-state">暂无护理文书，点击右上角「新建文书」创建</div>'; return; }
            const html = rows.map(x => `<div class="ndoc-item">
                <span class="ndoc-type-tag">${Format.escape(this.TYPE[x.doc_type] || x.doc_type)}</span>
                <span class="ndoc-title" title="${Format.escape(x.content)}">${Format.escape(x.title)}</span>
                <span class="ndoc-meta">${Format.escape((x.created_at || '').slice(0, 16))} · ${Format.escape(x.recorder_name || '')}</span>
                <button class="btn btn-small" onclick="NurseDocPage.view(${x.id})">查看</button>
                <button class="btn btn-small btn-danger" onclick="NurseDocPage.del(${x.id})">删除</button>
            </div>`).join('');
            box.innerHTML = '<div class="ndoc-list">' + html + '</div>';
        }).catch(() => { box.innerHTML = '<div class="empty-state">加载失败</div>'; });
    },

    reload() { if (this.patientId) this.load(this.patientId); },

    openAdd() {
        if (!this.patientId) { Dom.toast('请先选择患者', 'error'); return; }
        const content = `<form id="ndoc-form" class="form-grid">
            <div class="form-group"><label class="form-label">文书类型</label>
                <select name="doc_type" class="form-control">
                    <option value="admission_assessment">入院评估</option><option value="nursing_plan">护理计划</option>
                    <option value="health_edu">健康宣教</option><option value="transfer_record">转科记录</option>
                    <option value="other">其他</option>
                </select></div>
            <div class="form-group"><label class="form-label">标题 <span class="text-danger">*</span></label>
                <input name="title" class="form-control" required></div>
            <div class="form-group colspan-2"><label class="form-label">内容 <span class="text-danger">*</span></label>
                <textarea name="content" class="form-control" rows="8" required></textarea></div>
            <input type="hidden" name="patient_id" value="${this.patientId}">
        </form>`;
        const overlay = Dom.modal('常规护理文书', content, [
            { text: '保存', type: 'primary', onclick: async function () {
                const form = document.getElementById('ndoc-form');
                const data = {};
                new FormData(form).forEach((v, k) => data[k] = v);
                if (!data.title.trim() || !data.content.trim()) { Dom.toast('请填写标题与内容', 'error'); return; }
                try {
                    await AjaxLoader.api('nurse/nursing-docs', { method: 'POST', data: Object.assign(data, { action: 'save' }) });
                    Dom.toast('已保存', 'success');
                    overlay.remove();
                    NurseDocPage.reload();
                } catch (e) { /* 已提示 */ }
            } },
            { text: '取消', type: 'default' }
        ]);
    },

    view(id) {
        AjaxLoader.api('nurse/nursing-docs?action=list&patient_id=' + this.patientId, { silent: true }).then(r => {
            const d = (r.data || []).find(x => Number(x.id) === Number(id));
            if (!d) return;
            Dom.modal(Format.escape(d.title || ''), `
                <div class="kv-grid mb-10">
                    <div class="kv-item"><b>类型</b><span>${Format.escape(this.TYPE[d.doc_type] || d.doc_type)}</span></div>
                    <div class="kv-item"><b>记录人</b><span>${Format.escape(d.recorder_name || '')}</span></div>
                    <div class="kv-item"><b>时间</b><span>${Format.escape((d.created_at || '').slice(0, 16))}</span></div>
                </div>
                <div class="ndoc-content-view">${Format.escape(d.content || '')}</div>`,
                [{ text: '关闭', type: 'default' }]);
        }).catch(() => {});
    },

    async del(id) {
        Dom.confirm('删除护理文书', '确认删除该文书？', async function () {
                await AjaxLoader.api('nurse/nursing-docs', { method: 'POST', data: { action: 'delete', id } });
                Dom.toast('已删除', 'success');
                NurseDocPage.reload();
            }, { danger: true, okText: '删除' });
    }
};

/* SPA 页面初始化入口 (AjaxLoader 调用 window.nurse_nursing_docs_init) */
async function nurse_nursing_docs_init() {
    await NurseDocPage.init();
}
</script>