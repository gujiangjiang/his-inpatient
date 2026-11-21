<?php header("Content-Type: text/html; charset=utf-8"); require_once __DIR__ . '/../../icons/icon.php'; ?>
<div class="page-head flex-between">
    <div>
        <h3 class="page-title">体温单</h3>
        <p class="page-desc">按患者录入/查看每日各时点体温、脉搏、呼吸、血压、出入量</p>
    </div>
    <button class="btn btn-primary" onclick="NurseTempPage.openAdd()">录入体温</button>
</div>
<div class="card">
    <div class="flex-between mb-10">
        <select id="ntemp-patient" class="form-control" style="width:240px;max-width:100%" onchange="NurseTempPage.load(this.value)">
            <option value="">选择患者</option>
        </select>
        <button class="btn" onclick="NurseTempPage.reload()">刷新</button>
    </div>
    <div id="ntemp-box" class="empty-state">请选择患者查看体温单</div>
</div>

<script>
var NurseTempPage = {
    patientId: null,

    async init() {
        await App.mountShell('nurse');
        const r = await AjaxLoader.api('nurse/patient-list', { silent: true });
        const sel = document.getElementById('ntemp-patient');
        (r.data || []).forEach(p => {
            const opt = document.createElement('option');
            opt.value = p.id; opt.textContent = (p.bed_no || '') + ' 床 ' + p.name;
            sel.appendChild(opt);
        });
    },

    load(pid) {
        this.patientId = pid;
        if (!pid) { document.getElementById('ntemp-box').innerHTML = '<div class="empty-state">请选择患者查看体温单</div>'; return; }
        const box = document.getElementById('ntemp-box');
        box.innerHTML = '<div class="empty-state">加载中…</div>';
        AjaxLoader.api('nurse/temperature?action=list&patient_id=' + pid, { silent: true }).then(r => {
            const rows = r.data || [];
            if (!rows.length) { box.innerHTML = '<div class="empty-state">暂无体温记录，点击右上角「录入体温」添加</div>'; return; }
            const byDate = {};
            rows.forEach(x => { (byDate[x.record_date] = byDate[x.record_date] || []).push(x); });
            const dates = Object.keys(byDate).sort().reverse();
            const SLOTS = ['06:00', '10:00', '14:00', '18:00', '22:00'];
            const html = dates.map(d => {
                const day = byDate[d];
                const cells = SLOTS.map(s => {
                    const hit = day.find(x => x.time_slot === s);
                    return hit
                        ? `<td class="td-act td-val" onclick="NurseTempPage.edit(${hit.id})" title="点击修改">
                             ${hit.temperature != null ? Format.escape(String(hit.temperature)) + '°' : ''}
                             ${hit.pulse != null ? '<br><span class="fs-11">脉 ' + Format.escape(String(hit.pulse)) + '</span>' : ''}
                             ${hit.bp_systolic != null ? '<br><span class="fs-11">' + Format.escape(String(hit.bp_systolic)) + '/' + Format.escape(String(hit.bp_diastolic)) + '</span>' : ''}
                             ${hit.intake_ml != null ? '<br><span class="fs-11">入' + Format.escape(String(hit.intake_ml)) + 'ml</span>' : ''}
                             ${hit.output_ml != null ? '<br><span class="fs-11">出' + Format.escape(String(hit.output_ml)) + 'ml</span>' : ''}
                           </td>`
                        : `<td class="td-empty">-</td>`;
                }).join('');
                return `<tr><td class="td-time">${Format.escape(d)}</td>${cells}</tr>`;
            }).join('');
            box.innerHTML = `<div class="temp-grid-wrap">
                <table class="temp-grid">
                    <thead><tr><th>日期</th><th>06:00</th><th>10:00</th><th>14:00</th><th>18:00</th><th>22:00</th></tr></thead>
                    <tbody>${html}</tbody></table></div>`;
        }).catch(() => { box.innerHTML = '<div class="empty-state">加载失败</div>'; });
    },

    reload() { if (this.patientId) this.load(this.patientId); },

    openAdd() {
        if (!this.patientId) { Dom.toast('请先选择患者', 'error'); return; }
        const content = `<form id="ntemp-form" class="form-grid">
            ${Dom.formField({ name: 'record_date', label: '记录日期', type: 'date' }, '')}
            ${Dom.formField({ name: 'time_slot', label: '时点', type: 'select', options: [
                { label: '06:00', value: '06:00' }, { label: '10:00', value: '10:00' },
                { label: '14:00', value: '14:00' }, { label: '18:00', value: '18:00' },
                { label: '22:00', value: '22:00' }] }, '')}
            ${Dom.formField({ name: 'temperature', label: '体温(℃)', type: 'text' }, '')}
            ${Dom.formField({ name: 'pulse', label: '脉搏(次/分)', type: 'text' }, '')}
            ${Dom.formField({ name: 'respiratory_rate', label: '呼吸(次/分)', type: 'text' }, '')}
            ${Dom.formField({ name: 'bp_systolic', label: '收缩压', type: 'text' }, '')}
            ${Dom.formField({ name: 'bp_diastolic', label: '舒张压', type: 'text' }, '')}
            ${Dom.formField({ name: 'oxygen_saturation', label: '血氧(%)', type: 'text' }, '')}
            ${Dom.formField({ name: 'intake_ml', label: '入量(ml)', type: 'text' }, '')}
            ${Dom.formField({ name: 'output_ml', label: '出量(ml)', type: 'text' }, '')}
            <div class="form-group colspan-2"><label class="form-label">备注</label>
                <textarea name="notes" class="form-control" rows="2"></textarea></div>
            <input type="hidden" name="patient_id" value="${this.patientId}">
        </form>`;
        const overlay = Dom.modal('录入体温单', content, [
            { text: '保存', type: 'primary', onclick: async function () {
                const form = document.getElementById('ntemp-form');
                const data = {};
                new FormData(form).forEach((v, k) => data[k] = v);
                try {
                    await AjaxLoader.api('nurse/temperature', { method: 'POST', data: Object.assign(data, { action: 'save' }) });
                    Dom.toast('已保存', 'success');
                    overlay.remove();
                    NurseTempPage.reload();
                } catch (e) { /* 已提示 */ }
            } },
            { text: '取消', type: 'default' }
        ]);
    },

    edit(id) {
        const pid = this.patientId;
        AjaxLoader.api('nurse/temperature?action=list&patient_id=' + pid, { silent: true }).then(r => {
            const row = (r.data || []).find(x => Number(x.id) === Number(id));
            if (!row) return;
            const content = `<form id="ntemp-form" class="form-grid">
                ${Dom.formField({ name: 'record_date', label: '记录日期', type: 'date' }, row.record_date || '')}
                ${Dom.formField({ name: 'time_slot', label: '时点', type: 'select', options: [
                    { label: '06:00', value: '06:00' }, { label: '10:00', value: '10:00' },
                    { label: '14:00', value: '14:00' }, { label: '18:00', value: '18:00' },
                    { label: '22:00', value: '22:00' }] }, row.time_slot || '')}
                ${Dom.formField({ name: 'temperature', label: '体温(℃)', type: 'text' }, row.temperature != null ? row.temperature : '')}
                ${Dom.formField({ name: 'pulse', label: '脉搏(次/分)', type: 'text' }, row.pulse != null ? row.pulse : '')}
                ${Dom.formField({ name: 'respiratory_rate', label: '呼吸(次/分)', type: 'text' }, row.respiratory_rate != null ? row.respiratory_rate : '')}
                ${Dom.formField({ name: 'bp_systolic', label: '收缩压', type: 'text' }, row.bp_systolic != null ? row.bp_systolic : '')}
                ${Dom.formField({ name: 'bp_diastolic', label: '舒张压', type: 'text' }, row.bp_diastolic != null ? row.bp_diastolic : '')}
                ${Dom.formField({ name: 'oxygen_saturation', label: '血氧(%)', type: 'text' }, row.oxygen_saturation != null ? row.oxygen_saturation : '')}
                ${Dom.formField({ name: 'intake_ml', label: '入量(ml)', type: 'text' }, row.intake_ml != null ? row.intake_ml : '')}
                ${Dom.formField({ name: 'output_ml', label: '出量(ml)', type: 'text' }, row.output_ml != null ? row.output_ml : '')}
                <div class="form-group colspan-2"><label class="form-label">备注</label>
                    <textarea name="notes" class="form-control" rows="2">${Format.escape(row.notes || '')}</textarea></div>
                <input type="hidden" name="patient_id" value="${pid}">
                <input type="hidden" name="id" value="${id}">
            </form>`;
            const overlay = Dom.modal('修改体温记录', content, [
                { text: '保存', type: 'primary', onclick: async function () {
                    const form = document.getElementById('ntemp-form');
                    const data = {};
                    new FormData(form).forEach((v, k) => data[k] = v);
                    try {
                        await AjaxLoader.api('nurse/temperature', { method: 'POST', data: Object.assign(data, { action: 'save' }) });
                        Dom.toast('已保存', 'success');
                        overlay.remove();
                        NurseTempPage.reload();
                    } catch (e) { /* 已提示 */ }
                } },
                { text: '取消', type: 'default' }
            ]);
        }).catch(() => {});
    }
};

/* SPA 页面初始化入口 (AjaxLoader 调用 window.nurse_temperature_init) */
async function nurse_temperature_init() {
    await NurseTempPage.init();
}
</script>