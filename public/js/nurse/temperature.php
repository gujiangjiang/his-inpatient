<?php header("Content-Type: text/html; charset=utf-8"); require_once __DIR__ . '/../../icons/icon.php'; ?>
<div class="page-head flex-between">
    <div>
        <h3 class="page-title">体温单</h3>
        <p class="page-desc">从护理记录单自动派生，按 日期×时点 展示生命体征变化</p>
    </div>
    <div class="flex-between" style="gap:8px">
        <button class="btn" onclick="NurseTempPage.reload()">刷新</button>
        <button class="btn btn-primary" onclick="NurseTempPage.preview()">打印预览</button>
    </div>
</div>
<div class="card">
    <div class="flex-between mb-10">
        <select id="ntemp-patient" class="form-control" style="width:240px;max-width:100%" onchange="NurseTempPage.load(this.value)">
            <option value="">选择患者</option>
        </select>
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
            if (!rows.length) { box.innerHTML = '<div class="empty-state">暂无体温记录（护理记录单中填写生命体征后自动显示）</div>'; return; }
            const byDate = {};
            rows.forEach(x => { (byDate[(x.record_time || '').slice(0, 10)] = byDate[(x.record_time || '').slice(0, 10)] || []).push(x); });
            const dates = Object.keys(byDate).sort().reverse();
            const SLOTS = ['06:00', '10:00', '14:00', '18:00', '22:00'];
            const html = dates.map(d => {
                const day = byDate[d];
                const cells = SLOTS.map(s => {
                    const hit = day.find(x => (x.record_time || '').slice(11, 16) === s);
                    return hit
                        ? `<td class="td-val">
                             ${hit.temperature != null ? Format.escape(String(hit.temperature)) + '°' : ''}
                             ${hit.pulse != null ? '<br><span class="fs-11">脉 ' + Format.escape(String(hit.pulse)) + '</span>' : ''}
                             ${hit.bp_systolic != null ? '<br><span class="fs-11">' + Format.escape(String(hit.bp_systolic)) + '/' + Format.escape(String(hit.bp_diastolic)) + '</span>' : ''}
                             ${hit.oxygen_saturation != null ? '<br><span class="fs-11">SpO2 ' + Format.escape(String(hit.oxygen_saturation)) + '</span>' : ''}
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

    /* 打印预览 (A4, 可打印) */
    preview() {
        if (!this.patientId) { Dom.toast('请先选择患者', 'error'); return; }
        const sel = document.getElementById('ntemp-patient');
        const pname = sel && sel.selectedOptions.length ? sel.selectedOptions[0].textContent : '';
        AjaxLoader.api('nurse/temperature-chart?patient_id=' + this.patientId, { silent: true }).then(r => {
            const d = r.data || {};
            if (!d || !(d.days || []).length) { Dom.toast('暂无体温数据可预览', 'info'); return; }
            const pages = TempChart.buildPrintPages(d, d.patient || { patient_name: pname });
            if (!pages.length) { Dom.toast('暂无体温数据可预览', 'info'); return; }
            PrintPreview.combine('体温单 · 打印预览', pages, { canPrint: true });
        }).catch(() => {});
    }
};

/* SPA 页面初始化入口 (AjaxLoader 调用 window.nurse_temperature_init) */
async function nurse_temperature_init() {
    await NurseTempPage.init();
}
</script>
