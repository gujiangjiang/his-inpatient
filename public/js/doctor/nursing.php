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
        <div class="card-header flex-between">体温单（三测单） <span class="fs-12 text-muted">数据源：护理记录单 · 曲线/网格与护士站同源</span>
            <button class="btn btn-small btn-primary" onclick="DoctorNursing.tempPreview()">打印预览</button></div>
        <div id="nursing-temp-body"><div class="empty-state">加载中…</div></div>
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
    _tempStart: '',
    _tempData: null,

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

    /* ================= 体温单 (三测单, 与护士站同源: nurse/temperature-chart + TempChart) ================= */
    loadTemp() {
        const p = PatientContext.current();
        if (!p) return;
        const box = document.getElementById('nursing-temp-body');
        box.innerHTML = '<div class="empty-state">加载中…</div>';
        const url = 'nurse/temperature-chart?patient_id=' + p.id + (this._tempStart ? '&start=' + this._tempStart : '');
        AjaxLoader.api(url, { silent: true }).then(r => {
            /* 请求返回期间若已切换患者，丢弃旧响应 */
            const cur = PatientContext.current();
            if (!cur || String(cur.id) !== String(p.id) || !box.isConnected) return;
            const d = r.data || {};
            this._tempData = d;
            this._tempStart = d.start || this._tempStart;
            const hasVitals = (d.days || []).some(day => day.slots.some(s => s.temp || s.pulse != null || s.breath != null));
            const range = (d.start && d.end) ? Format.escape(d.start.slice(5) + ' ~ ' + d.end.slice(5)) : '';
            box.innerHTML = `<div class="temp-date-nav">
                <button class="btn btn-small" onclick="DoctorNursing.tempPage(-1)" ${!d.has_older ? 'disabled' : ''}>‹ 上一周</button>
                <span class="td-date">体温单 ${range}</span>
                <button class="btn btn-small" onclick="DoctorNursing.tempPage(1)" ${!d.has_newer ? 'disabled' : ''}>下一周 ›</button>
            </div><div class="temp-chart-host"></div>`;
            if (!hasVitals) {
                box.querySelector('.temp-chart-host').innerHTML = '<div class="empty-state">该患者此周无体征记录（护理记录单填写生命体征后自动生成）</div>';
                return;
            }
            TempChart.render(box.querySelector('.temp-chart-host'), d);
        }).catch(() => { box.innerHTML = '<div class="empty-state">加载失败</div>'; });
    },

    /* 体温单按周翻页 */
    tempPage(delta) {
        const d = this._tempData;
        if (!d || !d.start) return;
        const dt = new Date(d.start + 'T00:00:00');
        dt.setDate(dt.getDate() + delta * 7);
        const y = dt.getFullYear(), m = ('0' + (dt.getMonth() + 1)).slice(-2), dd = ('0' + dt.getDate()).slice(-2);
        this._tempStart = y + '-' + m + '-' + dd;
        this.loadTemp();
    },

    /* ================= 护理记录单 ================= */
    loadNotes() {
        const p = PatientContext.current();
        if (!p) return;
        const box = document.getElementById('nursing-notes-list');
        box.innerHTML = '<div class="nurse-op-actions"><button class="btn btn-small" onclick="DoctorNursing.notePreview()">打印预览</button></div><div class="empty-state">加载中…</div>';
        AjaxLoader.api('nurse/nursing-notes?action=list&patient_id=' + p.id, { silent: true }).then(r => {
            const rows = r.data || [];
            if (!rows.length) { box.innerHTML = '<div class="empty-state">暂无护理记录</div>'; return; }
            const CAT = { first: '首次', observation: '病情观察', measure: '测量', care: '护理措施', medication: '给药', transfer: '转科', other: '其他' };
            box.innerHTML = `<div class="nurse-op-actions"><button class="btn btn-small" onclick="DoctorNursing.notePreview()">打印预览</button></div><div class="nnote-list">` + rows.map(x => `<div class="nnote-row">
                <span class="nnote-time">${Format.escape((x.record_time || '').slice(0, 16))}</span>
                <span class="nnote-cat"><span class="badge badge-info">${Format.escape(CAT[x.category] || x.category)}</span></span>
                <span class="nnote-content">${Format.escape(x.note || x.content || '')}
                    <div class="nnote-meta">${DoctorNursing.noteMeta(x)}记录人：${Format.escape(x.recorder_name || '')}</div>
                </span>
            </div>`).join('') + '</div>';
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

    /* 护理记录详情元数据 (扩展字段) */
    noteMeta(x) {
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
        return m.length ? m.join('　') + '　' : '';
    },

    /* 护理记录单打印预览 (医生只读, 隐藏打印按钮) — 横板 A4 表格式, 与护士站同源 */
    notePreview() {
        const p = PatientContext.current();
        if (!p) return;
        AjaxLoader.api('nurse/nursing-notes?action=list&patient_id=' + p.id, { silent: true }).then(r => {
            const rows = r.data || [];
            if (!rows.length) { Dom.toast('暂无护理记录可预览', 'info'); return; }
            const pages = NursePrint.notesLandscapePages(rows, p);
            if (!pages.length) { Dom.toast('暂无护理记录可预览', 'info'); return; }
            PrintPreview.combine('护理记录单 · 打印预览', pages, { canPrint: false });
        }).catch(() => {});
    },

    /* 体温单打印预览 (医生只读, 隐藏打印按钮) — A4 三测单, 与护士站同源 */
    tempPreview() {
        const p = PatientContext.current();
        if (!p) return;
        const d = this._tempData;
        if (!d || !(d.days || []).length) { Dom.toast('暂无体温数据可预览', 'info'); return; }
        const pages = TempChart.buildPrintPages(d, p);
        if (!pages.length) { Dom.toast('暂无体温数据可预览', 'info'); return; }
        PrintPreview.combine('体温单 · 打印预览', pages, { canPrint: false });
    },

    viewDoc(id) {
        const p = PatientContext.current();
        if (!p) return;
        AjaxLoader.api('nurse/nursing-docs?action=list&patient_id=' + p.id, { silent: true }).then(r => {
            const d = (r.data || []).find(x => Number(x.id) === Number(id));
            if (!d) return;
            const TYPE = { admission_assessment: '入院评估', nursing_plan: '护理计划', health_edu: '健康宣教', transfer_record: '转科记录', other: '其他', generic: '通用' };
            const content = d.content || '';
            /* 新文书内容为模板 HTML: 原样渲染 (表格样式内联, 不依赖全局 CSS) */
            const isHtml = /<[a-z][\s\S]*>/i.test(content) && !/<textarea/i.test(content);
            const contentHtml = isHtml
                ? '<div class="ndoc-content-view ndoc-content-html">' + content + '</div>'
                : '<div class="ndoc-content-view">' + Format.escape(content) + '</div>';
            Dom.modal(Format.escape(d.title || ''), `
                <div class="kv-grid mb-10">
                    <div class="kv-item"><b>类型</b><span>${Format.escape(TYPE[d.doc_type] || d.doc_type)}</span></div>
                    <div class="kv-item"><b>记录人</b><span>${Format.escape(d.recorder_name || '')}</span></div>
                    <div class="kv-item"><b>时间</b><span>${Format.escape((d.created_at || '').slice(0, 16))}</span></div>
                </div>
                ${contentHtml}
                <style>.ndoc-content-html { max-height: 60vh; overflow: auto; } .ndoc-content-html table { border-collapse: collapse; width: 100%; font-size: 12px; } .ndoc-content-html th, .ndoc-content-html td { border: 1px solid #c8ccd4; padding: 5px 8px; text-align: left; vertical-align: top; } .ndoc-content-html th { background: #f5f7fa; font-weight: 600; }</style>`,
                [{ text: '关闭', type: 'default' }]);
        }).catch(() => {});
    }
};
/* SPA 页面初始化入口 (AjaxLoader 调用 window.doctor_nursing_init) */
async function doctor_nursing_init() {
    await DoctorNursing.init();
}
</script>