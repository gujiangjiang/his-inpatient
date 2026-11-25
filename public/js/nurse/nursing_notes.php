<?php header("Content-Type: text/html; charset=utf-8"); require_once __DIR__ . '/../../icons/icon.php'; ?>
<div class="page-head flex-between">
    <div>
        <h3 class="page-title">护理记录单</h3>
        <p class="page-desc">按患者记录护理观察、测量、措施、给药、转科等流水（时间必填，其余选填）</p>
    </div>
    <div class="flex-between" style="gap:8px">
        <button class="btn" onclick="NurseNotePage.preview()">打印预览</button>
        <button class="btn btn-primary" onclick="NurseNotePage.openAdd()">新增记录</button>
    </div>
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
    CAT: { first: '首次', observation: '病情观察', measure: '测量', care: '护理措施', medication: '给药', transfer: '转科', other: '其他' },
    PIPE: ['胃管', '尿管', '深静脉', '引流管', '其他'],
    CONS: ['清醒', '嗜睡', '昏睡', '昏迷', '模糊'],

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
                <span class="nnote-cat"><span class="badge ${x.is_first == 1 ? 'badge-primary' : 'badge-info'}">${Format.escape(this.CAT[x.category] || x.category)}</span></span>
                <span class="nnote-content">${Format.escape(x.note || x.content || '')}
                    <div class="nnote-meta">${this.metaText(x)}记录人：${Format.escape(x.recorder_name || '')}</div>
                </span>
                <button type="button" class="nnote-del" title="删除" onclick="NurseNotePage.del(${x.id})">×</button>
            </div>`).join('');
            box.innerHTML = '<div class="nnote-list">' + html + '</div>';
        }).catch(() => { box.innerHTML = '<div class="empty-state">加载失败</div>'; });
    },

    metaText(x) {
        const m = [];
        if (x.temperature != null) m.push('体温 ' + Format.escape(String(x.temperature)) + '°');
        if (x.pulse != null) m.push('脉搏 ' + Format.escape(String(x.pulse)));
        if (x.respiratory_rate != null) m.push('呼吸 ' + Format.escape(String(x.respiratory_rate)));
        if (x.bp_systolic != null) m.push('血压 ' + Format.escape(String(x.bp_systolic)) + '/' + Format.escape(String(x.bp_diastolic)));
        if (x.oxygen_saturation != null) m.push('血氧 ' + Format.escape(String(x.oxygen_saturation)) + '%');
        if (x.intake_ml != null) m.push('入量 ' + Format.escape(String(x.intake_ml)) + 'ml');
        if (x.output_ml != null) m.push('出量 ' + Format.escape(String(x.output_ml)) + 'ml');
        if (x.has_pipeline) m.push('管路 ' + Format.escape(x.has_pipeline) + (x.pipeline_desc ? '(' + Format.escape(x.pipeline_desc) + ')' : ''));
        if (x.drainage_ml != null) m.push('引流 ' + Format.escape(String(x.drainage_ml)) + 'ml');
        if (x.consciousness) m.push('意识 ' + Format.escape(x.consciousness));
        if (x.blood_glucose != null) m.push('血糖 ' + Format.escape(String(x.blood_glucose)));
        if (x.pupil) m.push('瞳孔 ' + Format.escape(x.pupil));
        if (x.gcs_eye != null) m.push('GCS ' + Format.escape(String(x.gcs_eye)) + '/' + Format.escape(String(x.gcs_verbal)) + '/' + Format.escape(String(x.gcs_motor)));
        if (x.is_first == 1) { if (x.height != null) m.push('身高 ' + Format.escape(String(x.height)) + 'cm'); if (x.weight != null) m.push('体重 ' + Format.escape(String(x.weight)) + 'kg'); }
        return m.length ? m.join('　') + '　' : '';
    },

    reload() { if (this.patientId) this.load(this.patientId); },

    /* 打印预览 (A4, 可打印) */
    preview() {
        if (!this.patientId) { Dom.toast('请先选择患者', 'error'); return; }
        const sel = document.getElementById('nnote-patient');
        const pname = sel && sel.selectedOptions.length ? sel.selectedOptions[0].textContent : '';
        AjaxLoader.api('nurse/nursing-notes?action=list&patient_id=' + this.patientId, { silent: true }).then(r => {
            const rows = r.data || [];
            if (!rows.length) { Dom.toast('暂无护理记录可预览', 'info'); return; }
            const pages = NursePrint.notesLandscapePages(rows, { patient_name: pname });
            if (!pages.length) { Dom.toast('暂无护理记录可预览', 'info'); return; }
            PrintPreview.combine('护理记录单 · 打印预览', pages, { canPrint: true });
        }).catch(() => {});
    },

    openAdd() {
        if (!this.patientId) { Dom.toast('请先选择患者', 'error'); return; }
        const content = `<form id="nnote-form" class="nnote-form">
            <div class="form-group"><label class="form-label">记录时间 <span class="text-danger">*</span></label>
                <input type="text" name="record_time" class="form-control" data-dtpicker="datetime" value="${Format.nowStamp()}"></div>
            <div class="form-group"><label class="form-label">类别</label>
                <select name="category" class="form-control">
                    <option value="observation">病情观察</option><option value="measure">测量</option>
                    <option value="care">护理措施</option><option value="medication">给药</option>
                    <option value="transfer">转科</option><option value="other">其他</option>
                </select></div>
            <div class="form-group"><label class="form-label">体温(℃)</label><input name="temperature" class="form-control"></div>
            <div class="form-group"><label class="form-label">脉搏(次/分)</label><input name="pulse" class="form-control"></div>
            <div class="form-group"><label class="form-label">呼吸(次/分)</label><input name="respiratory_rate" class="form-control"></div>
            <div class="form-group"><label class="form-label">血压</label><span class="bp-pair"><input name="bp_systolic" class="form-control" placeholder="收缩压" style="width:60%"><span class="fs-12 text-muted">/</span><input name="bp_diastolic" class="form-control" placeholder="舒张压" style="width:35%"></span></div>
            <div class="form-group"><label class="form-label">血氧(%)</label><input name="oxygen_saturation" class="form-control"></div>
            <div class="form-group"><label class="form-label">入量(ml)</label><input name="intake_ml" class="form-control"></div>
            <div class="form-group"><label class="form-label">出量(ml)</label><input name="output_ml" class="form-control"></div>
            <div class="form-group"><label class="form-label">意识情况</label>
                <select name="consciousness" class="form-control"><option value="">选填</option>${this.CONS.map(c => '<option>' + c + '</option>').join('')}</select></div>
            <div class="form-group"><label class="form-label">管路</label>
                <select name="has_pipeline" class="form-control"><option value="">无</option>${this.PIPE.map(c => '<option>' + c + '</option>').join('')}</select></div>
            <div class="form-group"><label class="form-label">管路描述</label><input name="pipeline_desc" class="form-control" placeholder="如: 胃管留置中"></div>
            <div class="form-group"><label class="form-label">引流量(ml)</label><input name="drainage_ml" class="form-control"></div>
            <div class="form-group"><label class="form-label">血糖(mmol/L)</label><input name="blood_glucose" class="form-control"></div>
            <div class="form-group"><label class="form-label">瞳孔情况</label><input name="pupil" class="form-control" placeholder="如: 等大等圆 对光反射灵敏"></div>
            <div class="form-group"><label class="form-label">GCS 睁眼</label><select name="gcs_eye" class="form-control"><option value="">-</option><option value="4">4 自发</option><option value="3">3 呼唤</option><option value="2">2 疼痛</option><option value="1">1 无</option></select></div>
            <div class="form-group"><label class="form-label">GCS 语言</label><select name="gcs_verbal" class="form-control"><option value="">-</option><option value="5">5 正常</option><option value="4">4 混乱</option><option value="3">3 词语</option><option value="2">2 声音</option><option value="1">1 无</option></select></div>
            <div class="form-group"><label class="form-label">GCS 运动</label><select name="gcs_motor" class="form-control"><option value="">-</option><option value="6">6 遵嘱</option><option value="5">5 定位</option><option value="4">4 回缩</option><option value="3">3 屈曲</option><option value="2">2 过伸</option><option value="1">1 无</option></select></div>
            <div class="form-group colspan-2"><label class="form-label">护理记录 <span class="text-danger">*</span></label>
                <textarea name="note" class="form-control" rows="3" required></textarea></div>
            <input type="hidden" name="patient_id" value="${this.patientId}">
        </form>`;
        const overlay = Dom.modal('新增护理记录', content, [
            { text: '保存', type: 'primary', onclick: async function () {
                const form = document.getElementById('nnote-form');
                const data = {};
                new FormData(form).forEach((v, k) => data[k] = v);
                if (!data.note.trim()) { Dom.toast('请输入护理记录内容', 'error'); return; }
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

/* SPA 页面初始化入口 (AjaxLoader 调用 window.nurse_nursing_notes_init) */
async function nurse_nursing_notes_init() {
    await NurseNotePage.init();
}
</script>
