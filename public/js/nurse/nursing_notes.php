<?php header("Content-Type: text/html; charset=utf-8"); require_once __DIR__ . '/../../icons/icon.php'; ?>
<div class="page-head flex-between">
    <div>
        <h3 class="page-title">护理记录单</h3>
        <p class="page-desc">按患者记录护理观察、测量、措施、给药、转科等流水</p>
    </div>
    <button class="btn btn-primary" onclick="NurseNotePage.openAdd()">新增记录</button>
</div>
<div class="card">
    <div class="flex-between mb-10">
        <select id="nnote-patient" class="form-control" style="width:240px;max-width:100%" onchange="NurseNotePage.load(this.value)">
            <option value="">选择患者</option>
        </select>
        <button class="btn" onclick="NurseNotePage.reload()">刷新</button>
    </div>
    <div id="nnote-box" class="empty-state">请选择患者查看护理记录</div>
</div>

<script>
var NurseNotePage = {
    patientId: null,
    CAT: { observation: '病情观察', measure: '测量', care: '护理措施', medication: '给药', transfer: '转科', other: '其他' },

    async init() {
        await App.mountShell('nurse');
        const r = await AjaxLoader.api('nurse/patient-list', { silent: true });
        const sel = document.getElementById('nnote-patient');
        (r.data || []).forEach(p => {
            const opt = document.createElement('option');
            opt.value = p.id; opt.textContent = (p.bed_no || '') + ' 床 ' + p.name;
            sel.appendChild(opt);
        });
    },

    load(pid) {
        this.patientId = pid;
        if (!pid) { document.getElementById('nnote-box').innerHTML = '<div class="empty-state">请选择患者查看护理记录</div>'; return; }
        const box = document.getElementById('nnote-box');
        box.innerHTML = '<div class="empty-state">加载中…</div>';
        AjaxLoader.api('nurse/nursing-notes?action=list&patient_id=' + pid, { silent: true }).then(r => {
            const rows = r.data || [];
            if (!rows.length) { box.innerHTML = '<div class="empty-state">暂无护理记录，点击右上角「新增记录」添加</div>'; return; }
            const html = rows.map(x => `<div class="nnote-row">
                <span class="nnote-time">${Format.escape((x.record_time || '').slice(0, 16))}</span>
                <span class="nnote-cat"><span class="badge badge-info">${Format.escape(this.CAT[x.category] || x.category)}</span></span>
                <span class="nnote-content">${Format.escape(x.content)}
                    <div class="nnote-meta">${x.temperature != null ? '体温 ' + Format.escape(String(x.temperature)) + '° ' : ''}${x.pulse != null ? '脉搏 ' + Format.escape(String(x.pulse)) + ' ' : ''}${x.bp_systolic != null ? '血压 ' + Format.escape(String(x.bp_systolic)) + '/' + Format.escape(String(x.bp_diastolic)) + ' ' : ''}${x.intake_ml != null ? '入量 ' + Format.escape(String(x.intake_ml)) + 'ml ' : ''}${x.output_ml != null ? '出量 ' + Format.escape(String(x.output_ml)) + 'ml ' : ''}记录人：${Format.escape(x.recorder_name || '')}</div>
                </span>
                <button type="button" class="nnote-del" title="删除" onclick="NurseNotePage.del(${x.id})">×</button>
            </div>`).join('');
            box.innerHTML = '<div class="nnote-list">' + html + '</div>';
        }).catch(() => { box.innerHTML = '<div class="empty-state">加载失败</div>'; });
    },

    reload() { if (this.patientId) this.load(this.patientId); },

    openAdd() {
        if (!this.patientId) { Dom.toast('请先选择患者', 'error'); return; }
        const content = `<form id="nnote-form" class="nnote-form">
            <div class="form-group"><label class="form-label">记录时间</label>
                <input type="text" name="record_time" class="form-control" data-dtpicker="datetime" value="${Format.nowStamp()}"></div>
            <div class="form-group"><label class="form-label">类别</label>
                <select name="category" class="form-control">
                    <option value="observation">病情观察</option><option value="measure">测量</option>
                    <option value="care">护理措施</option><option value="medication">给药</option>
                    <option value="transfer">转科</option><option value="other">其他</option>
                </select></div>
            <div class="form-group"><label class="form-label">体温(℃)</label><input name="temperature" class="form-control"></div>
            <div class="form-group"><label class="form-label">脉搏(次/分)</label><input name="pulse" class="form-control"></div>
            <div class="form-group"><label class="form-label">收缩压</label><input name="bp_systolic" class="form-control"></div>
            <div class="form-group"><label class="form-label">舒张压</label><input name="bp_diastolic" class="form-control"></div>
            <div class="form-group"><label class="form-label">入量(ml)</label><input name="intake_ml" class="form-control"></div>
            <div class="form-group"><label class="form-label">出量(ml)</label><input name="output_ml" class="form-control"></div>
            <div class="form-group colspan-2"><label class="form-label">记录内容 <span class="text-danger">*</span></label>
                <textarea name="content" class="form-control" rows="3" required></textarea></div>
            <input type="hidden" name="patient_id" value="${this.patientId}">
        </form>`;
        const overlay = Dom.modal('护理记录单', content, [
            { text: '保存', type: 'primary', onclick: async function () {
                const form = document.getElementById('nnote-form');
                const data = {};
                new FormData(form).forEach((v, k) => data[k] = v);
                if (!data.content.trim()) { Dom.toast('请输入记录内容', 'error'); return; }
                try {
                    await AjaxLoader.api('nurse/nursing-notes', { method: 'POST', data: Object.assign(data, { action: 'save' }) });
                    Dom.toast('已保存', 'success');
                    overlay.remove();
                    NurseNotePage.reload();
                } catch (e) { /* 已提示 */ }
            } },
            { text: '取消', type: 'default' }
        ]);
        if (window.DateTimePicker) DateTimePicker.initInputs(overlay);
    },

    async del(id) {
        Dom.confirm('删除护理记录', '确认删除该条护理记录？', async function () {
                await AjaxLoader.api('nurse/nursing-notes', { method: 'POST', data: { action: 'delete', id } });
                Dom.toast('已删除', 'success');
                NurseNotePage.reload();
            }, { danger: true, okText: '删除' });
    }
};
</script>