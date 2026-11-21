<?php header("Content-Type: text/html; charset=utf-8"); require_once __DIR__ . '/../../icons/icon.php'; ?>
<div class="page-head flex-between">
    <div>
        <h3 class="page-title">护理</h3>
        <p class="page-desc" id="nursing-pt-desc">患者护理文书：体温单 / 护理记录单 / 常规护理文书</p>
    </div>
    <button class="btn" onclick="DoctorNursing.refresh()">刷新</button>
</div>

<div class="tabs">
    <button class="tab active" id="nursing-tab-temp" onclick="DoctorNursing.switch('temp')">体温单</button>
    <button class="tab" id="nursing-tab-notes" onclick="DoctorNursing.switch('notes')">护理记录单</button>
    <button class="tab" id="nursing-tab-docs" onclick="DoctorNursing.switch('docs')">护理文书</button>
</div>

<div id="nursing-pane-temp">
    <div class="card">
        <div class="card-header">体温单折线图 <span class="fs-12 text-muted">体温（红）· 脉搏（蓝）· 呼吸（绿）</span></div>
        <div id="nursing-temp-chart" class="temp-chart-wrap"><div class="empty-state">暂无体温数据</div></div>
    </div>
    <div class="card">
        <div class="card-header">体温单明细</div>
        <div id="nursing-temp-grid" class="temp-grid-wrap"><div class="empty-state">暂无体温数据</div></div>
    </div>
</div>

<div id="nursing-pane-notes" class="hidden">
    <div class="card">
        <div class="card-header">护理记录单</div>
        <div id="nursing-notes-list" class="nnote-list"><div class="empty-state">暂无护理记录</div></div>
    </div>
</div>

<div id="nursing-pane-docs" class="hidden">
    <div class="card">
        <div class="card-header">常规护理文书</div>
        <div id="nursing-docs-list" class="ndoc-list"><div class="empty-state">暂无护理文书</div></div>
    </div>
</div>

<script>
var DoctorNursing = {
    activeTab: 'temp',

    async init() {
        await App.mountShell('doctor');
        const p = PatientContext.current();
        if (!p) { Dom.toast('请先选择患者', 'error'); return; }
        const desc = document.getElementById('nursing-pt-desc');
        if (desc) desc.textContent = p.name + '（' + Format.gender(p.gender) + ' ' + (p.admission_no || '') + '）· 护理文书';
        this.refresh();
    },

    switch(tab) {
        this.activeTab = tab;
        ['temp', 'notes', 'docs'].forEach(t => {
            const btn = document.getElementById('nursing-tab-' + t);
            const pane = document.getElementById('nursing-pane-' + t);
            if (btn) btn.classList.toggle('active', t === tab);
            if (pane) pane.classList.toggle('hidden', t !== tab);
        });
        if (tab === 'temp') this.loadTemp();
        else if (tab === 'notes') this.loadNotes();
        else this.loadDocs();
    },

    refresh() { this.switch(this.activeTab); },

    /* ================= 体温单 ================= */
    loadTemp() {
        const p = PatientContext.current();
        if (!p) return;
        AjaxLoader.api('nurse/temperature?action=list&patient_id=' + p.id, { silent: true }).then(r => {
            const rows = r.data || [];
            this.renderChart(rows);
            this.renderGrid(rows);
        }).catch(() => {});
    },

    /* SVG 折线图: 横轴日期时点, 纵轴体温刻度 35-42℃ + 脉搏 40-120 + 呼吸 10-40 */
    renderChart(rows) {
        const box = document.getElementById('nursing-temp-chart');
        if (!rows.length) { box.innerHTML = '<div class="empty-state">暂无体温数据</div>'; return; }
        const SLOTS = ['06:00', '10:00', '14:00', '18:00', '22:00'];
        // 按日期+时点排序的测量序列 (折线点)
        const seq = rows.slice().sort((a, b) =>
            (a.record_date + a.time_slot).localeCompare(b.record_date + b.time_slot));
        const dates = [...new Set(rows.map(x => x.record_date))].sort();
        const W = Math.max(680, dates.length * 64);
        const H = 300;
        const padL = 40, padR = 20, padT = 20, padB = 44;
        const plotW = W - padL - padR;
        const plotH = H - padT - padB | 0;
        // 坐标映射
        const xAt = i => padL + (i / Math.max(1, seq.length - 1)) * plotW;
        const yTemp = v => padT + plotH - ((Math.min(42, Math.max(35, v)) - 35) / 7) * plotH;
        const yPulse = v => padT + plotH - ((Math.min(120, Math.max(40, v)) - 40) / 80) * plotH;
        const yResp = v => padT + plotH - ((Math.min(40, Math.max(10, v)) - 10) / 30) * plotH;

        // 纵轴刻度 (体温)
        let gridV = '';
        for (let t = 35; t <= 42; t++) {
            const y = yTemp(t);
            gridV += `<line x1="${padL}" y1="${y}" x2="${W - padR}" y2="${y}" stroke="#eef2f7" stroke-width="1"/>`;
            gridV += `<text x="${padL - 4}" y="${y + 3}" text-anchor="end" font-size="10" fill="#909399">${t}</text>`;
        }
        // 横轴日期
        let gridH = '';
        seq.forEach((s, i) => {
            const x = xAt(i);
            gridH += `<line x1="${x}" y1="${padT}" x2="${x}" y2="${padT + plotH}" stroke="#f5f7fa" stroke-width="1"/>`;
            gridH += `<text x="${x}" y="${H - padB + 14}" text-anchor="middle" font-size="10" fill="#606266">${s.record_date.slice(5)} ${s.time_slot}</text>`;
        });

        // 折线生成
        const poly = (vals, yFn, color) => {
            const pts = vals.map((v, i) => (v == null ? null : `${xAt(i).toFixed(1)},${yFn(Number(v)).toFixed(1)}`));
            const clean = pts.filter(Boolean);
            return `<polyline fill="none" stroke="${color}" stroke-width="2" stroke-linejoin="round" points="${clean.join(' ')}"/>` +
                vals.map((v, i) => v == null ? '' :
                    `<circle cx="${xAt(i).toFixed(1)}" cy="${yFn(Number(v)).toFixed(1)}" r="2.5" fill="${color}"/>`).join('');
        };
        const tempPts = seq.map(s => s.temperature);
        const pulsePts = seq.map(s => s.pulse);
        const respPts = seq.map(s => s.respiratory_rate);
        box.innerHTML = `<svg class="temp-chart-svg" viewBox="0 0 ${W} ${H}" preserveAspectRatio="xMinYMin meet" style="width:100%;height:auto">
            <rect x="0" y="0" width="${W}" height="${H}" fill="#fff"/>
            ${gridV}${gridH}
            ${poly(respPts, yResp, '#2fb344')}
            ${poly(pulsePts, yPulse, '#409eff')}
            ${poly(tempPts, yTemp, '#e5484d')}
        </svg>`;
    },

    /* 体温单明细网格 */
    renderGrid(rows) {
        const box = document.getElementById('nursing-temp-grid');
        if (!rows.length) { box.innerHTML = '<div class="empty-state">暂无体温数据</div>'; return; }
        const SLOTS = ['06:00', '10:00', '14:00', '18:00', '22:00'];
        const byDate = {};
        rows.forEach(x => { (byDate[x.record_date] = byDate[x.record_date] || []).push(x); });
        const dates = Object.keys(byDate).sort().reverse();
        const html = dates.map(d => {
            const day = byDate[d];
            const cells = SLOTS.map(s => {
                const hit = day.find(x => x.time_slot === s);
                return hit
                    ? `<td class="td-val">${hit.temperature != null ? Format.escape(String(hit.temperature)) + '°' : ''}${hit.pulse != null ? '<br><span class="fs-11">脉' + Format.escape(String(hit.pulse)) + '</span>' : ''}${hit.bp_systolic != null ? '<br><span class="fs-11">' + Format.escape(String(hit.bp_systolic)) + '/' + Format.escape(String(hit.bp_diastolic)) + '</span>' : ''}${hit.intake_ml != null ? '<br><span class="fs-11">入' + Format.escape(String(hit.intake_ml)) + '</span>' : ''}${hit.output_ml != null ? '<br><span class="fs-11">出' + Format.escape(String(hit.output_ml)) + '</span>' : ''}</td>`
                    : '<td class="td-empty">-</td>';
            }).join('');
            return `<tr><td class="td-time">${Format.escape(d)}</td>${cells}</tr>`;
        }).join('');
        box.innerHTML = `<table class="temp-grid">
            <thead><tr><th>日期</th><th>06:00</th><th>10:00</th><th>14:00</th><th>18:00</th><th>22:00</th></tr></thead>
            <tbody>${html}</tbody></table>`;
    },

    /* ================= 护理记录单 ================= */
    loadNotes() {
        const p = PatientContext.current();
        if (!p) return;
        const box = document.getElementById('nursing-notes-list');
        AjaxLoader.api('nurse/nursing-notes?action=list&patient_id=' + p.id, { silent: true }).then(r => {
            const rows = r.data || [];
            if (!rows.length) { box.innerHTML = '<div class="empty-state">暂无护理记录</div>'; return; }
            const CAT = { observation: '病情观察', measure: '测量', care: '护理措施', medication: '给药', transfer: '转科', other: '其他' };
            box.innerHTML = rows.map(x => `<div class="nnote-row">
                <span class="nnote-time">${Format.escape((x.record_time || '').slice(0, 16))}</span>
                <span class="nnote-cat"><span class="badge badge-info">${Format.escape(CAT[x.category] || x.category)}</span></span>
                <span class="nnote-content">${Format.escape(x.content)}
                    <div class="nnote-meta">${x.temperature != null ? '体温 ' + Format.escape(String(x.temperature)) + '° ' : ''}${x.pulse != null ? '脉搏 ' + Format.escape(String(x.pulse)) + ' ' : ''}${x.intake_ml != null ? '入量 ' + Format.escape(String(x.intake_ml)) + 'ml ' : ''}记录人：${Format.escape(x.recorder_name || '')}</div>
                </span>
            </div>`).join('');
        }).catch(() => { box.innerHTML = '<div class="empty-state">加载失败</div>'; });
    },

    /* ================= 常规护理文书 ================= */
    loadDocs() {
        const p = PatientContext.current();
        if (!p) return;
        const box = document.getElementById('nursing-docs-list');
        AjaxLoader.api('nurse/nursing-docs?action=list&patient_id=' + p.id, { silent: true }).then(r => {
            const rows = r.data || [];
            if (!rows.length) { box.innerHTML = '<div class="empty-state">暂无护理文书</div>'; return; }
            const TYPE = { admission_assessment: '入院评估', nursing_plan: '护理计划', health_edu: '健康宣教', transfer_record: '转科记录', other: '其他' };
            box.innerHTML = rows.map(x => `<div class="ndoc-item">
                <span class="ndoc-type-tag">${Format.escape(TYPE[x.doc_type] || x.doc_type)}</span>
                <span class="ndoc-title" title="${Format.escape(x.content)}">${Format.escape(x.title)}</span>
                <span class="ndoc-meta">${Format.escape((x.created_at || '').slice(0, 16))} · ${Format.escape(x.recorder_name || '')}</span>
                <button class="btn btn-small" onclick="DoctorNursing.viewDoc(${x.id})">查看</button>
            </div>`).join('');
        }).catch(() => { box.innerHTML = '<div class="empty-state">加载失败</div>'; });
    },

    viewDoc(id) {
        const p = PatientContext.current();
        if (!p) return;
        AjaxLoader.api('nurse/nursing-docs?action=list&patient_id=' + p.id, { silent: true }).then(r => {
            const d = (r.data || []).find(x => Number(x.id) === Number(id));
            if (!d) return;
            const TYPE = { admission_assessment: '入院评估', nursing_plan: '护理计划', health_edu: '健康宣教', transfer_record: '转科记录', other: '其他' };
            Dom.modal(Format.escape(d.title || ''), `
                <div class="kv-grid mb-10">
                    <div class="kv-item"><b>类型</b><span>${Format.escape(TYPE[d.doc_type] || d.doc_type)}</span></div>
                    <div class="kv-item"><b>记录人</b><span>${Format.escape(d.recorder_name || '')}</span></div>
                    <div class="kv-item"><b>时间</b><span>${Format.escape((d.created_at || '').slice(0, 16))}</span></div>
                </div>
                <div class="ndoc-content-view">${Format.escape(d.content || '')}</div>`,
                [{ text: '关闭', type: 'default' }]);
        }).catch(() => {});
    }
};
/* SPA 页面初始化入口 (AjaxLoader 调用 window.doctor_nursing_init) */
async function doctor_nursing_init() {
    await DoctorNursing.init();
}
</script>