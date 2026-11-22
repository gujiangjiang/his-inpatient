<?php /* 床位卡片操作面板片段 (被 nurse/station.php 包含): 集中 查医嘱/查病历/查检验检查/摆药/护理文书/出科出院 */
require_once __DIR__ . '/../../icons/icon.php'; ?>
<script>
var NurseBedPanel = {
    patient: null,
    activeTab: 'orders',

    openFromCard(el) {
        try {
            const p = JSON.parse(el.getAttribute('data-patient'));
            this.open(p);
        } catch (e) {
            Dom.toast('患者数据异常', 'error');
        }
    },

    open(patient) {
        /* 单例患者面板：禁止旧面板残留后继续操作新患者，避免患者上下文串用。 */
        if (this._overlay && this._overlay.parentNode) this._overlay.remove();
        this.patient = patient;
        this.activeTab = 'info';
        const p = patient;
        const level = String(p.nursing_level || '3');
        const roman = { special: '特', '1': 'Ⅰ', '2': 'Ⅱ', '3': 'Ⅲ' }[level] || 'Ⅲ';
        const head = `
            <div class="kv-grid mb-10">
                <div class="kv-item"><b>床号</b><span>${Format.escape(roman + ' | ' + (p.bed_no || '-'))}</span></div>
                <div class="kv-item"><b>姓名</b><span>${Format.escape(p.patient_name || p.name || '')}</span></div>
                <div class="kv-item"><b>性别</b><span>${Format.gender(p.gender)}</span></div>
                <div class="kv-item"><b>年龄</b><span>${Format.ageClinical(p.birth_date) || p.age || '-'}</span></div>
                <div class="kv-item"><b>住院号</b><span>${Format.escape(p.admission_no || '-')}</span></div>
                <div class="kv-item"><b>入院</b><span>${Format.escape((p.admission_date || '').slice(0, 10) || '-')}</span></div>
                <div class="kv-item"><b>费别</b><span>${Format.escape(p.fee_type || '-')}</span></div>
                <div class="kv-item"><b>诊断</b><span>${Format.escape(p.admission_diagnosis || '-')}</span></div>
            </div>
            <div class="nurse-op-tabs" id="nbp-tabs">
                <button type="button" class="nurse-op-tab active" data-tab="info" onclick="NurseBedPanel.switch('info')">患者信息</button>
                <button type="button" class="nurse-op-tab" data-tab="orders" onclick="NurseBedPanel.switch('orders')">医嘱</button>
                <button type="button" class="nurse-op-tab" data-tab="records" onclick="NurseBedPanel.switch('records')">病历</button>
                <button type="button" class="nurse-op-tab" data-tab="lab" onclick="NurseBedPanel.switch('lab')">检验检查</button>
                <button type="button" class="nurse-op-tab" data-tab="medicine" onclick="NurseBedPanel.switch('medicine')">摆药</button>
                <button type="button" class="nurse-op-tab" data-tab="temperature" onclick="NurseBedPanel.switch('temperature')">体温单</button>
                <button type="button" class="nurse-op-tab" data-tab="notes" onclick="NurseBedPanel.switch('notes')">护理记录</button>
                <button type="button" class="nurse-op-tab" data-tab="docs" onclick="NurseBedPanel.switch('docs')">护理文书</button>
                <button type="button" class="nurse-op-tab" data-tab="ward" onclick="NurseBedPanel.switch('ward')">出科出院</button>
            </div>
            <div class="nurse-op-body" id="nbp-body"><div class="empty-state">加载中…</div></div>`;
        const overlay = Dom.modal('床位操作 - ' + (p.patient_name || p.name || ''), head, [
            { text: '关闭', type: 'default' }
        ]);
        overlay.querySelector('.modal').classList.add('modal-lg');
        this._overlay = overlay;
        this.loadTab('info');
    },

    switch(tab) {
        this.activeTab = tab;
        const tabs = document.querySelectorAll('#nbp-tabs .nurse-op-tab');
        if (tabs.length) tabs.forEach(t => t.classList.toggle('active', t.dataset.tab === tab));
        this.loadTab(tab);
    },

    loadTab(tab) {
        const box = document.getElementById('nbp-body');
        if (!box) return;
        if (tab === 'info') this.loadInfo(box);
        else if (tab === 'orders') this.loadOrders(box);
        else if (tab === 'records') this.loadRecords(box);
        else if (tab === 'lab') this.loadLabExam(box);
        else if (tab === 'medicine') this.loadMedicine(box);
        else if (tab === 'temperature') this.loadTemperature(box);
        else if (tab === 'notes') this.loadNotes(box);
        else if (tab === 'docs') this.loadDocs(box);
        else if (tab === 'ward') this.loadWard(box);
    },

    close() {
        if (this._overlay && this._overlay.parentNode) this._overlay.remove();
    },

    refresh() {
        this.loadTab(this.activeTab);
    },

    /* ================= 患者信息 (基本信息 + 身高体重补填 + 腕带预留) ================= */
    loadInfo(box) {
        const pid = this.patient.id;
        const p = this.patient;
        box.innerHTML = '<div class="empty-state">加载中…</div>';
        AjaxLoader.api('nurse/patient-info?action=get&patient_id=' + pid, { silent: true }).then(r => {
            const d = r.data || {};
            const age = Format.ageClinical(d.birth_date);
            const roman = { special: '特', '1': 'Ⅰ', '2': 'Ⅱ', '3': 'Ⅲ' }[String(d.nursing_level || '3')] || 'Ⅲ';
            const infoHtml = `<div class="nurse-op-actions">
                <button class="btn btn-small" onclick="NurseBedPanel.infoEdit()">修改个人信息</button>
                <button class="btn btn-small" onclick="NurseBedPanel.wristbandPreview()">腕带打印</button>
            </div>
            <div class="kv-grid">
                <div class="kv-item"><b>姓名</b><span>${Format.escape(d.name || '')}</span></div>
                <div class="kv-item"><b>性别</b><span>${Format.gender(d.gender)}</span></div>
                <div class="kv-item"><b>年龄</b><span>${age || '-'}</span></div>
                <div class="kv-item"><b>出生日期</b><span>${Format.escape(d.birth_date || '-')}</span></div>
                <div class="kv-item"><b>身份证号</b><span>${Format.escape(d.id_card || '-')}</span></div>
                <div class="kv-item"><b>联系电话</b><span>${Format.escape(d.phone || '-')}</span></div>
                <div class="kv-item"><b>住院号</b><span>${Format.escape(d.admission_no || '-')}</span></div>
                <div class="kv-item"><b>床号</b><span>${Format.escape(d.bed_no || '-')} 床</span></div>
                <div class="kv-item"><b>病区</b><span>${Format.escape(d.ward_name || '-')}</span></div>
                <div class="kv-item"><b>科室</b><span>${Format.escape(d.department_name || '-')}</span></div>
                <div class="kv-item"><b>护理级别</b><span>${roman}</span></div>
                <div class="kv-item"><b>费别</b><span>${Format.escape(d.fee_type || '-')}</span></div>
                <div class="kv-item"><b>入院日期</b><span>${Format.escape((d.admission_date || '').slice(0, 10))}</span></div>
                <div class="kv-item"><b>入院诊断</b><span>${Format.escape(d.admission_diagnosis || '-')}</span></div>
                <div class="kv-item"><b>过敏史</b><span>${Format.escape(d.allergy_history || '无')}</span></div>
                <div class="kv-item"><b>身高</b><span>${d.height != null ? Format.escape(String(d.height)) + ' cm' : '<span class="text-muted">未登记</span>'}</span></div>
                <div class="kv-item"><b>体重</b><span>${d.weight != null ? Format.escape(String(d.weight)) + ' kg' : '<span class="text-muted">未登记</span>'}</span></div>
            </div>
            <p class="fs-12 text-muted" style="margin-top:10px">提示：身高体重可补填；病历文书中引用身高体重的元素，只要在创建文书前已登记即可自动引用。</p>`;
            box.innerHTML = infoHtml;
        }).catch(() => { box.innerHTML = '<div class="empty-state">患者信息加载失败</div>'; });
    },

    /* 补填/修改身高体重 (写入首次护理记录) */
    infoEdit() {
        const self = this;
        const pid = this.patient.id;
        AjaxLoader.api('nurse/patient-info?action=get&patient_id=' + pid, { silent: true }).then(r => {
            const d = r.data || {};
            const content = `<form id="info-form" class="form-grid">
                <div class="form-group"><label class="form-label">联系电话</label><input name="phone" class="form-control" value="${Format.escape(d.phone || '')}"></div>
                <div class="form-group"><label class="form-label">身高(cm)</label><input name="height" type="number" step="0.1" class="form-control" value="${d.height != null ? d.height : ''}"></div>
                <div class="form-group"><label class="form-label">体重(kg)</label><input name="weight" type="number" step="0.1" class="form-control" value="${d.weight != null ? d.weight : ''}"></div>
                <input type="hidden" name="patient_id" value="${pid}">
            </form>`;
            const overlay = Dom.modal('补填/修改身高体重', content, [
                { text: '保存', type: 'primary', onclick: async function () {
                    const form = document.getElementById('info-form');
                    const data = {};
                    new FormData(form).forEach((v, k) => data[k] = v);
                    try {
                        await AjaxLoader.api('nurse/patient-info', { method: 'POST', data: Object.assign(data, { action: 'save' }) });
                        Dom.toast('已保存', 'success');
                        overlay.remove();
                        self.refresh();
                    } catch (e) { /* 已提示 */ }
                } },
                { text: '取消', type: 'default' }
            ]);
        }).catch(() => {});
    },

    /* 患者腕带：打印前重新读取完整患者资料，避免卡片摘要缺少过敏史等字段。 */
    wristbandPreview() {
        const pid = this.patient && this.patient.id;
        if (!pid) { Dom.toast('未选择患者', 'error'); return; }
        AjaxLoader.api('nurse/patient-info?action=get&patient_id=' + pid, { silent: true }).then(r => {
            if (!this.patient || String(this.patient.id) !== String(pid)) {
                Dom.toast('患者已切换，已取消腕带预览', 'info');
                return;
            }
            const template = PrintLabels.wristband(r.data || {});
            if (!template) { Dom.toast('患者缺少住院号，无法生成腕带', 'error'); return; }
            PrintLabels.open(template);
        }).catch(() => { Dom.toast('患者腕带信息加载失败', 'error'); });
    },

    /* ================= 医嘱 ================= */
    loadOrders(box) {
        const pid = this.patient.id;
        box.innerHTML = '<div class="empty-state">加载中…</div>';
        AjaxLoader.api('nurse/order-list?patient_id=' + pid, { silent: true }).then(r => {
            /* 请求返回期间若已切换患者，丢弃旧响应，不能写入当前患者面板。 */
            if (!this.patient || String(this.patient.id) !== String(pid) || !box.isConnected) return;
            const rows = r.data || [];
            const pendingN = rows.filter(o => o.status === 'pending').length;
            const catLabel = c => c === 'medication' ? '药品' : '医嘱';
            const actHtml = o => {
                let action = '';
                if (o.status === 'invalid') action = '<span class="fs-12 text-muted">已作废</span>';
                else if (o.status === 'invalid_pending') action = `<button class="btn btn-small btn-danger" onclick="NurseBedPanel.confirmOrder(${o.id},'invalid')">确认作废</button>`;
                else if (o.status === 'stop_pending') action = `<button class="btn btn-small btn-danger" onclick="NurseBedPanel.confirmOrder(${o.id},'stopped')">确认停止</button>`;
                else if (o.status === 'completed') action = '<span class="fs-12 text-muted">已完成</span>';
                else if (o.status === 'stopped') action = '<span class="fs-12 text-muted">已停止</span>';
                else if (o.status === 'executing') action = '<span class="fs-12 text-muted">执行中</span>';
                else if (o.status === 'pending') action = `<button class="btn btn-small btn-success" onclick="NurseBedPanel.verifyOrder(${o.id})">核查</button>`;
                else if (o.category === 'medication' && !o.dispensed_id) action = '<span class="fs-12 text-muted">待发药后执行</span>';
                else action = `<button class="btn btn-small btn-success" onclick="NurseBedPanel.executeOrder(${o.id})">执行</button>`;
                const canPrint = o.category === 'medication' && !o.parent_id && o.dispensed_id
                    && ['verified', 'executing', 'completed'].includes(o.status);
                const label = canPrint
                    ? `<button class="btn btn-small" onclick="NurseBedPanel.printMedicationLabel(${o.id})">执行贴</button>` : '';
                return [label, action].filter(Boolean).join(' ');
            };
            const rowCls = o => o.status === 'invalid' ? 'os-txt-invalid'
                : (o.status === 'invalid_pending' ? 'os-txt-invalid_pending' : (o.status === 'stop_pending' ? 'os-txt-stop_pending' : ''));
            const body = rows.length
                ? rows.map(o => `<tr class="${rowCls(o)}">
                    <td>${Format.escape(o.content)}</td>
                    <td>${catLabel(o.category)}</td>
                    <td>${Format.escape([o.usage_name, o.freq_code].filter(Boolean).join(' · '))}</td>
                    <td>${Format.escape(o.doctor_name || '-')}</td>
                    <td>${Format.escape(o.stopped_name || '-')}</td>
                    <td><span class="badge ${Format.orderStatusClass(o.status)}">${Format.orderStatus(o.status)}</span></td>
                    <td>${actHtml(o)}</td></tr>`).join('')
                : '<tr><td colspan="7"><div class="empty-state">暂无医嘱</div></td></tr>';
            const toolbar = `<div class="nurse-op-actions">
                    ${pendingN > 0 ? `<button class="btn btn-primary" onclick="NurseBedPanel.verifyAll()">一键核对（${pendingN} 条）</button>` : ''}
                    <button class="btn" onclick="NurseBedPanel.printOrderSheets()">医嘱单打印</button>
                  </div>`;
            box.innerHTML = toolbar + `<div style="overflow-x:auto"><table class="table"><thead><tr><th>医嘱</th><th>类型</th><th>途径 · 频次</th><th>开嘱医生</th><th>停嘱医生</th><th>状态</th><th>操作</th></tr></thead><tbody>${body}</tbody></table></div>`;
        }).catch(() => { box.innerHTML = '<div class="empty-state">加载失败</div>'; });
    },

    /* 一键核对: 批量核查该患者全部待核查医嘱 */
    async verifyAll() {
        const pid = this.patient.id;
        Dom.confirm('一键核对', '将核查该患者全部待核查医嘱，确认继续？', async function () {
                try {
                    const r = await AjaxLoader.api('nurse/verify-batch', { method: 'POST', data: { patient_id: pid } });
                    Dom.toast(r.message || '已核对', 'success');
                    NurseBedPanel.refresh();
                    if (typeof NurseStation.refresh === 'function') NurseStation.refresh();
                } catch (e) { /* 已提示 */ }
            }, { okText: '一键核对' });
    },

    /* 医嘱单打印: 长期/临时两份独立 A4 文档 (各自计算页码, 忽略作废医嘱) */
    printOrderSheets() {
        const pid = this.patient.id;
        const p = this.patient;
        AjaxLoader.api('nurse/order-sheet?patient_id=' + pid, { silent: true }).then(r => {
            const d = r.data || {};
            const responsePid = d.patient && d.patient.id;
            if (!this.patient || String(this.patient.id) !== String(pid) || String(responsePid) !== String(pid)) {
                Dom.toast('患者已切换，已取消本次医嘱单预览，请重新操作', 'info');
                return;
            }
            const longRows = d.long || [], tempRows = d.temp || [];
            if (!longRows.length && !tempRows.length) { Dom.toast('该患者暂无有效医嘱', 'info'); return; }
            const info = {
                name: p.patient_name || p.name || '', gender: Format.gender(p.gender),
                age: Format.ageClinical(p.birth_date) || p.age || '', department: p.department_name || '',
                ward: p.ward_name || '', bed: p.bed_no || '', admission_no: p.admission_no || '',
                hospital: ''
            };
            /* 长期在前、临时在后, 拼接为一份打印文档 (页码连续), 单一预览框 */
            const htmls = [];
            if (longRows.length) htmls.push(NurseBedPanel.buildOrderSheetHtml('长期医嘱单', longRows, d.patient || info, true));
            if (tempRows.length) htmls.push(NurseBedPanel.buildOrderSheetHtml('临时医嘱单', tempRows, d.patient || info, false));
            PrintPreview.combine('医嘱单 · 打印预览', htmls, { canPrint: true });
        }).catch(() => { Dom.toast('医嘱单数据加载失败', 'error'); });
    },

    /* 组装医嘱单 A4 文档 (抬头与电子病历一致; hasStop: 长期单含停止时间列, 临时单无) */
    buildOrderSheetHtml(title, rows, patient, hasStop) {
        const esc = function (s) { return Format.escape(String(s == null ? '' : s)); };
        const info = PrintPreview.patientInfo(patient, title);
        const header = PrintPreview.sheetHeader(info);
        const body = rows.map(function (o) {
            const content = esc(o.content) + (o.dosage ? ' ' + esc(o.dosage) + (o.dosage_unit ? esc(o.dosage_unit) : '') : '');
            const usage = [o.usage_name, o.usage_rate].filter(Boolean).join(' ');
            const freq = [o.freq_code, o.freq_detail].filter(Boolean).join(' ');
            const start = o.start_date ? esc(String(o.start_date).slice(0, 16)) : '';
            const status = o.status === 'stopped' ? '（已停）' : '';
            const cells = hasStop
                ? '<td class="os-time">' + start + '</td><td class="os-time">' + esc(o.stop_date ? String(o.stop_date).slice(0, 16) : (o.end_date ? String(o.end_date).slice(0, 16) : '')) + '</td>'
                : '<td class="os-time">' + start + '</td>';
            return '<tr>' + cells
                + '<td class="os-content">' + content + status + '</td>'
                + '<td class="os-usage">' + esc(usage) + '</td>'
                + '<td class="os-freq">' + esc(freq) + '</td>'
                + '<td class="os-sign">' + esc(o.doctor_name || '') + '</td>'
                + (hasStop ? '<td class="os-sign">' + esc(o.stopped_name || '') + '</td>' : '')
                + '<td class="os-sign">' + esc(o.verified_name || '') + '</td>'
                + '<td class="os-sign">' + esc(o.exec_name || '') + '</td>'
                + '</tr>';
        }).join('');
        const headCells = hasStop
            ? '<th style="width:10%">开始时间</th><th style="width:10%">停止时间</th><th style="width:26%">医嘱内容</th><th style="width:10%">用法</th><th style="width:8%">频次</th><th style="width:9%">开嘱医生</th><th style="width:9%">停嘱医生</th><th style="width:9%">核对护士</th><th style="width:9%">执行护士</th>'
            : '<th style="width:14%">开始时间</th><th style="width:36%">医嘱内容</th><th style="width:13%">用法</th><th style="width:10%">频次</th><th style="width:9%">开嘱医生</th><th style="width:10%">核对护士</th><th style="width:8%">执行护士</th>';
        const sheet = '<div class="page">' + header
            + '<div class="pg-body"><table class="os-table">'
            + '<thead><tr>' + headCells + '</tr></thead>'
            + '<tbody>' + body + '</tbody></table></div>'
            + '<div class="pg-foot"><span class="emr-fp-left"></span><span class="emr-fp-page">第 1 页 / 共 1 页</span></div></div>';
        return '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="UTF-8"><title>' + esc(title) + '</title>'
            + '<style>' + NurseBedPanel.orderSheetCss() + '</style></head><body>' + sheet + '</body></html>';
    },

    orderSheetCss() {
        return [
            '@page { size: A4; margin: 0; }',
            'html, body { margin: 0; padding: 0; }',
            '* { box-sizing: border-box; }',
            'body { background: #e9edf2; }',
            '.page { position: relative; width: 794px; height: 1123px; overflow: hidden; margin: 0 auto; background: #fff; }',
            '.pg-head { position: absolute; left: 36px; right: 36px; top: 20px; text-align: center; }',
            '.brand-name-lockup { display:inline-grid; grid-template-columns:minmax(0,1fr); width:max-content; max-width:100%; min-width:0; }',
            '.brand-name-line { width:100%; min-width:0; text-align:justify; text-align-last:justify; text-justify:inter-character; }',
            '.emr-brand-lockup { display:grid; margin:0 auto; }',
            '.emr-hos { font-size: 20pt; font-weight: bold; letter-spacing: 4px; line-height: 1.4; }',
            '.emr-hos2 { font-size: 12pt; font-weight: normal; line-height: 1.35; }',
            '.emr-docname { font-size: 16pt; font-weight: bold; letter-spacing: 3px; line-height: 1.5; }',
            '.emr-infobar { display: flex; flex-wrap: wrap; justify-content: space-between; gap: 2px 20px; margin-top: 8px; padding: 6px 4px 6px; border-top: 1.5px solid #000; border-bottom: 1.5px solid #000; font-size: 11pt; }',
            '.pg-body { position: absolute; left: 36px; top: 158px; width: 722px; height: 906px; overflow: hidden; }',
            '.os-table { width: 100%; border-collapse: collapse; table-layout: fixed; font-size: 10pt; }',
            '.os-table th, .os-table td { border: 0.5pt solid #000; padding: 4px 5px; vertical-align: top; text-align: left; overflow-wrap: anywhere; word-break: break-all; }',
            '.os-table th { background: #f2f2f2; text-align: center; font-weight: 600; }',
            '.os-content { font-size: 10pt; line-height: 1.5; }',
            '.os-usage, .os-freq { font-size: 9.5pt; }',
            '.os-time { font-size: 9.5pt; line-height: 1.5; }',
            '.os-sign { text-align: center; font-size: 9.5pt; }',
            '.pg-foot { position: absolute; left: 36px; right: 36px; bottom: 22px; height: 30px; display: flex; justify-content: space-between; align-items: flex-end; font-size: 10pt; color: #000; }',
            '.pg-foot .emr-fp-page { position: absolute; left: 0; right: 0; bottom: 0; text-align: center; }'
        ].join('\n');
    },

    async verifyOrder(id) {
        await AjaxLoader.api('orders/verify', { method: 'POST', data: { id } });
        Dom.toast('已核查', 'success');
        this.refresh();
        if (typeof NurseStation.refresh === 'function') NurseStation.refresh();
    },
    async executeOrder(id) {
        await AjaxLoader.api('nurse/nursing-action', { method: 'POST', data: { order_id: id, action: 'execute' } });
        Dom.toast('执行成功', 'success');
        this.refresh();
        if (typeof NurseStation.refresh === 'function') NurseStation.refresh();
    },
    printMedicationLabel(id) {
        MedicationLabel.preview(id, { expectedPatientId: this.patient && this.patient.id });
    },
    async confirmOrder(id, status) {
        await AjaxLoader.api('orders/verify', { method: 'POST', data: { id, status } });
        Dom.toast(status === 'invalid' ? '已确认作废' : '已确认停止', 'success');
        this.refresh();
        if (typeof NurseStation.refresh === 'function') NurseStation.refresh();
    },

    /* ================= 病历 (只读) ================= */
    loadRecords(box) {
        const pid = this.patient.id;
        box.innerHTML = '<div class="empty-state">加载中…</div>';
        AjaxLoader.api('emr/document-list?patient_id=' + pid, { silent: true }).then(r => {
            const docs = r.data || [];
            if (!docs.length) { box.innerHTML = '<div class="empty-state">暂无病历文书</div>'; return; }
            const rows = docs.map(d => {
                const tag = d.status === 'signed' ? '<span class="badge badge-success">已签名</span>'
                    : '<span class="badge badge-warning">草稿</span>';
                return `<tr>
                    <td>${Format.escape(d.title || d.template_name || '未命名文书')}</td>
                    <td>${Format.escape(d.category_name || '')}</td>
                    <td>${Format.escape((d.updated_at || '').slice(0, 16))}</td>
                    <td>${tag}</td>
                    <td><button class="btn btn-small" onclick="NurseBedPanel.viewRecord(${d.id})">查看</button></td>
                </tr>`;
            }).join('');
            box.innerHTML = `<table class="table"><thead><tr><th>文书</th><th>分类</th><th>更新时间</th><th>状态</th><th>操作</th></tr></thead><tbody>${rows}</tbody></table>`;
        }).catch(() => { box.innerHTML = '<div class="empty-state">加载失败</div>'; });
    },

    viewRecord(id) {
        AjaxLoader.api('emr/document-get?id=' + id, { silent: true }).then(r => {
            const d = r.data;
            if (!d) return;
            const p = this.patient;
            // 患者信息 (供打印快照/重排页眉)
            const info = {
                name: p.patient_name || p.name || '',
                gender: Format.gender(p.gender),
                age: Format.ageClinical(p.birth_date) || p.age || '',
                department: p.department_name || '',
                ward_name: p.ward_name || '',
                bed_no: p.bed_no || '',
                admission_no: p.admission_no || ''
            };
            let html = '';
            if (d.content_html && d.content_html.indexOf('<!DOCTYPE') !== -1) {
                // 已签名快照: 直接复用完整打印文档
                html = d.content_html;
            } else if (d.doc_json) {
                // 未签名/无快照: 重新分页生成 (与医生打印一致, 预览不自动弹打印)
                try { html = EmrPrint.buildHTML(d.doc_json, info, { noPrint: true }); } catch (e) { /* 回退 */ }
            }
            // iframe 预览: 剥离脚本 (快照可能含自动打印 onload)
            html = String(html || '').replace(/<script[\s\S]*?<\/script>/gi, '');
            if (!html) { Dom.toast('该文书暂无内容可预览', 'info'); return; }
            // 统一打印预览: 护士查看病历仅供预览, 不显示打印按钮
            PrintPreview.open('病历预览 - ' + Format.escape(d.title || ''), html, { canPrint: false });
        }).catch(() => {});
    },

    /* ================= 检验检查 ================= */
    loadLabExam(box) {
        const pid = this.patient.id;
        box.innerHTML = '<div class="empty-state">加载中…</div>';
        Promise.all([
            AjaxLoader.api('lab/list?patient_id=' + pid, { silent: true }).catch(() => ({ data: [] })),
            AjaxLoader.api('exam/list?patient_id=' + pid, { silent: true }).catch(() => ({ data: [] }))
        ]).then(([lr, er]) => {
            const labs = lr.data || [];
            const exams = er.data || [];
            const LAB = { pending: '待采集', collected: '已采集', reported: '已报告' };
            const EXM = { pending: '待执行', reported: '已报告' };
            const labRows = labs.length ? labs.map(x => `<tr>
                <td>${Format.escape(x.report_no)}</td><td>${Format.escape(x.exam_name)}</td>
                <td>${Format.escape(x.specimen_type || '')}</td>
                <td>${LabBarcode.statusHtml(x)}</td>
                <td><span class="badge ${x.status === 'reported' ? 'badge-success' : (x.status === 'pending' ? 'badge-pending' : 'badge-info')}">${LAB[x.status] || Format.escape(x.status)}</span></td>
                <td><button class="btn btn-small" onclick="NurseBedPanel.printLabBarcode(${x.id})">${LabBarcode.buttonText(x)}</button>
                    ${x.status === 'pending' ? `<button class="btn btn-small btn-success" onclick="NurseBedPanel.collectLab(${x.id})">采集</button>` : ''}</td>
            </tr>`).join('')
                : '<tr><td colspan="6"><div class="empty-state">暂无检验</div></td></tr>';
            const examRows = exams.length ? exams.map(x => `<tr>
                <td>${Format.escape(x.exam_no || x.id)}</td><td>${Format.escape(x.exam_type)}</td>
                <td>${Format.escape(x.exec_dept || '')}</td>
                <td><span class="badge ${x.status === 'reported' ? 'badge-success' : 'badge-pending'}">${EXM[x.status] || Format.escape(x.status)}</span></td>
                <td>${x.status === 'reported' ? '<button class="btn btn-small" onclick="NurseBedPanel.viewExam(' + x.id + ')">详情</button>' : '<span class="fs-12 text-muted">-</span>'}</td>
            </tr>`).join('')
                : '<tr><td colspan="5"><div class="empty-state">暂无检查</div></td></tr>';
            box.innerHTML = `
                <div class="card-header" style="border-bottom:1px solid #eef2f7">检验申请</div>
                <div style="overflow-x:auto"><table class="table"><thead><tr><th>单号</th><th>项目</th><th>标本</th><th>条码</th><th>状态</th><th>操作</th></tr></thead><tbody>${labRows}</tbody></table></div>
                <div class="card-header" style="border-bottom:1px solid #eef2f7;margin-top:14px">检查申请</div>
                <div style="overflow-x:auto"><table class="table"><thead><tr><th>单号</th><th>项目</th><th>执行科室</th><th>状态</th><th>操作</th></tr></thead><tbody>${examRows}</tbody></table></div>`;
        });
    },

    async collectLab(id) {
        await AjaxLoader.api('lab/collect', { method: 'POST', data: { id } });
        Dom.toast('已采集', 'success');
        this.refresh();
    },
    printLabBarcode(id) {
        const pid = this.patient && this.patient.id;
        LabBarcode.preview(id, {
            expectedPatientId: pid,
            onPrinted: function () {
                if (NurseBedPanel.patient && String(NurseBedPanel.patient.id) === String(pid)) NurseBedPanel.refresh();
            }
        });
    },
    viewExam(id) {
        AjaxLoader.api('exam/detail?id=' + id, { silent: true }).then(r => {
            const d = r.data || {};
            Dom.modal('检查详情', `
                <div class="kv-grid mb-10">
                    <div class="kv-item"><b>项目</b><span>${Format.escape(d.exam_type || '')}</span></div>
                    <div class="kv-item"><b>状态</b><span>${d.status === 'reported' ? '已报告' : '待执行'}</span></div>
                    <div class="kv-item"><b>结论</b><span>${Format.escape(d.result || '-')}</span></div>
                </div>
                <div class="ndoc-content-view">${Format.escape(d.report || '')}</div>`,
                [{ text: '关闭', type: 'default' }]);
        }).catch(() => {});
    },

    /* ================= 摆药 (该患者已核对未发药 → 申请预摆药) ================= */
    loadMedicine(box) {
        const pid = this.patient.id;
        box.innerHTML = '<div class="empty-state">加载中…</div>';
        AjaxLoader.api('nurse/prepare?action=list', { silent: true }).then(r => {
            const rows = (r.data || []).filter(x => String(x.patient_id) === String(pid));
            if (!rows.length) { box.innerHTML = '<div class="empty-state">该患者暂无待摆药药品医嘱</div>'; return; }
            const trs = rows.map(o => `<tr>
                <td><input type="checkbox" class="nbp-med-check" value="${o.id}" ${o.dispense_status === 'prepared' ? 'disabled' : ''}></td>
                <td>${Format.escape(o.content)}${o.dosage ? ' ' + Format.escape(o.dosage) : ''}</td>
                <td>${Format.escape(o.freq_code || '')}</td>
                <td>${o.dispense_status === 'prepared' ? '<span class="badge badge-warning">已预摆</span>' : '<span class="badge badge-default">未预摆</span>'}</td>
                <td>${o.dispense_status === 'prepared' ? '<button class="btn btn-small" onclick="NurseBedPanel.cancelPrepare(' + o.id + ')">取消</button>' : ''}</td>
            </tr>`).join('');
            box.innerHTML = `<div class="nurse-op-actions">
                <button class="btn btn-primary" onclick="NurseBedPanel.batchPrepare()">申请预摆药（勾选）</button>
            </div>
            <table class="table"><thead><tr><th></th><th>医嘱</th><th>频次</th><th>预摆</th><th>操作</th></tr></thead><tbody>${trs}</tbody></table>`;
        }).catch(() => { box.innerHTML = '<div class="empty-state">加载失败</div>'; });
    },

    batchPrepare() {
        const ids = [...document.querySelectorAll('.nbp-med-check:checked')].map(c => Number(c.value));
        if (!ids.length) { Dom.toast('请先勾选要预摆药的医嘱', 'error'); return; }
        const plan = document.getElementById('nbp-plan-time');
        AjaxLoader.api('nurse/prepare', { method: 'POST', data: { action: 'apply', ids, plan_time: plan ? plan.value : '' } }).then(() => {
            Dom.toast('已申请预摆药', 'success');
            this.refresh();
            if (typeof NurseStation.refresh === 'function') NurseStation.refresh();
        }).catch(() => {});
    },

    async cancelPrepare(id) {
        await AjaxLoader.api('nurse/prepare', { method: 'POST', data: { action: 'cancel', id } });
        Dom.toast('已取消预摆', 'success');
        this.refresh();
    },

    /* ================= 体温单 (从护理记录单派生, 只读) ================= */
    loadTemperature(box) {
        const pid = this.patient.id;
        box.innerHTML = `<div class="temp-date-nav">
            <span class="td-date">体温单</span>
            <button class="btn btn-small btn-primary" onclick="NurseBedPanel.tempPreview()">打印预览</button>
        </div><div class="empty-state">加载中…</div>`;
        AjaxLoader.api('nurse/temperature?action=list&patient_id=' + pid, { silent: true }).then(r => {
            const rows = r.data || [];
            const e = box.querySelector('.empty-state');
            if (!rows.length) { if (e) e.textContent = '暂无体温记录（护理记录单中填写生命体征后自动显示）'; return; }
            if (e) e.outerHTML = '';
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
            box.insertAdjacentHTML('beforeend', `<div class="temp-grid-wrap">
                <table class="temp-grid">
                    <thead><tr><th>日期</th><th>06:00</th><th>10:00</th><th>14:00</th><th>18:00</th><th>22:00</th></tr></thead>
                    <tbody>${html}</tbody></table></div>`);
        }).catch(() => { const e2 = box.querySelector('.empty-state'); if (e2) e2.textContent = '加载失败'; });
    },

    tempPreview() {
        const pid = this.patient.id;
        const p = this.patient;
        AjaxLoader.api('nurse/temperature?action=list&patient_id=' + pid, { silent: true }).then(r => {
            const rows = r.data || [];
            if (!rows.length) { Dom.toast('暂无体温数据可预览', 'info'); return; }
            const html = NursePrint.tempHtml(rows, { patient_name: p.patient_name || p.name || '' });
            PrintPreview.open('体温单 · 打印预览', html, { canPrint: true });
        }).catch(() => {});
    },

    /* ================= 护理记录单 (扩展字段: 生命体征/管路/引流/意识/血糖/瞳孔/GCS) ================= */
    loadNotes(box) {
        const pid = this.patient.id;
        box.innerHTML = `<div class="nurse-op-actions">
            <button class="btn btn-primary" onclick="NurseBedPanel.noteAdd()">新增记录</button>
            <button class="btn" onclick="NurseBedPanel.notePreview()">打印预览</button>
        </div><div class="empty-state">加载中…</div>`;
        AjaxLoader.api('nurse/nursing-notes?action=list&patient_id=' + pid, { silent: true }).then(r => {
            const rows = r.data || [];
            const e = box.querySelector('.empty-state');
            if (!rows.length) { if (e) e.textContent = '暂无护理记录'; return; }
            if (e) e.outerHTML = '';
            const CAT = { first: '首次', observation: '病情观察', measure: '测量', care: '护理措施', medication: '给药', transfer: '转科', other: '其他' };
            const html = rows.map(x => `<div class="nnote-row">
                <span class="nnote-time">${Format.escape((x.record_time || '').slice(0, 16))}</span>
                <span class="nnote-cat"><span class="badge ${x.is_first == 1 ? 'badge-primary' : 'badge-info'}">${Format.escape(CAT[x.category] || x.category)}</span></span>
                <span class="nnote-content">${Format.escape(x.note || x.content || '')}
                    <div class="nnote-meta">${NurseBedPanel.noteMeta(x)}记录人：${Format.escape(x.recorder_name || '')}</div>
                </span>
                <button type="button" class="nnote-del" title="删除" onclick="NurseBedPanel.noteDel(${x.id})">×</button>
            </div>`).join('');
            box.insertAdjacentHTML('beforeend', '<div class="nnote-list">' + html + '</div>');
        }).catch(() => { const e = box.querySelector('.empty-state'); if (e) e.textContent = '加载失败'; });
    },

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
        if (x.is_first == 1) { if (x.height != null) m.push('身高 ' + Format.escape(String(x.height)) + 'cm'); if (x.weight != null) m.push('体重 ' + Format.escape(String(x.weight)) + 'kg'); }
        return m.length ? m.join('　') + '　' : '';
    },

    notePreview() {
        const pid = this.patient.id;
        const p = this.patient;
        AjaxLoader.api('nurse/nursing-notes?action=list&patient_id=' + pid, { silent: true }).then(r => {
            const rows = r.data || [];
            if (!rows.length) { Dom.toast('暂无护理记录可预览', 'info'); return; }
            const html = NursePrint.notesHtml(rows, { patient_name: p.patient_name || p.name || '' });
            PrintPreview.open('护理记录单 · 打印预览', html, { canPrint: true });
        }).catch(() => {});
    },

    noteAdd() { this.noteForm(null); },
    noteForm(id) {
        const pid = this.patient.id;
        const content = `<form id="note-form" class="nnote-form">
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
                <select name="consciousness" class="form-control"><option value="">选填</option><option>清醒</option><option>嗜睡</option><option>昏睡</option><option>昏迷</option><option>模糊</option></select></div>
            <div class="form-group"><label class="form-label">管路</label>
                <select name="has_pipeline" class="form-control"><option value="">无</option><option>胃管</option><option>尿管</option><option>深静脉</option><option>引流管</option><option>其他</option></select></div>
            <div class="form-group"><label class="form-label">管路描述</label><input name="pipeline_desc" class="form-control" placeholder="如: 胃管留置中"></div>
            <div class="form-group"><label class="form-label">引流量(ml)</label><input name="drainage_ml" class="form-control"></div>
            <div class="form-group"><label class="form-label">血糖(mmol/L)</label><input name="blood_glucose" class="form-control"></div>
            <div class="form-group"><label class="form-label">瞳孔情况</label><input name="pupil" class="form-control" placeholder="如: 等大等圆 对光反射灵敏"></div>
            <div class="form-group"><label class="form-label">GCS 睁眼</label><select name="gcs_eye" class="form-control"><option value="">-</option><option value="4">4 自发</option><option value="3">3 呼唤</option><option value="2">2 疼痛</option><option value="1">1 无</option></select></div>
            <div class="form-group"><label class="form-label">GCS 语言</label><select name="gcs_verbal" class="form-control"><option value="">-</option><option value="5">5 正常</option><option value="4">4 混乱</option><option value="3">3 词语</option><option value="2">2 声音</option><option value="1">1 无</option></select></div>
            <div class="form-group"><label class="form-label">GCS 运动</label><select name="gcs_motor" class="form-control"><option value="">-</option><option value="6">6 遵嘱</option><option value="5">5 定位</option><option value="4">4 回缩</option><option value="3">3 屈曲</option><option value="2">2 过伸</option><option value="1">1 无</option></select></div>
            <div class="form-group colspan-2"><label class="form-label">护理记录 <span class="text-danger">*</span></label>
                <textarea name="note" class="form-control" rows="3" required></textarea></div>
            <input type="hidden" name="patient_id" value="${pid}">
            ${id ? '<input type="hidden" name="id" value="' + id + '">' : ''}
        </form>`;
        const overlay = Dom.modal('护理记录单', content, [
            { text: '保存', type: 'primary', onclick: async function () {
                const form = document.getElementById('note-form');
                const data = {};
                new FormData(form).forEach((v, k) => data[k] = v);
                if (!data.note.trim()) { Dom.toast('请输入护理记录内容', 'error'); return; }
                try {
                    await AjaxLoader.api('nurse/nursing-notes', { method: 'POST', data: Object.assign(data, { action: 'save' }) });
                    Dom.toast('已保存', 'success');
                    overlay.remove();
                    NurseBedPanel.refresh();
                } catch (e) { /* 已提示 */ }
            } },
            { text: '取消', type: 'default' }
        ]);
        if (window.DateTimePicker) DateTimePicker.initInputs(overlay);
    },

    async noteDel(id) {
        Dom.confirm('删除护理记录', '确认删除该条护理记录？', async function () {
                await AjaxLoader.api('nurse/nursing-notes', { method: 'POST', data: { action: 'delete', id } });
                Dom.toast('已删除', 'success');
                NurseBedPanel.refresh();
            }, { danger: true, okText: '删除' });
    },

    /* ================= 常规护理文书 ================= */
    loadDocs(box) {
        const pid = this.patient.id;
        box.innerHTML = `<div class="nurse-op-actions">
            <button class="btn btn-primary" onclick="NurseBedPanel.docAdd()">新建文书</button>
        </div><div class="empty-state">加载中…</div>`;
        AjaxLoader.api('nurse/nursing-docs?action=list&patient_id=' + pid, { silent: true }).then(r => {
            const rows = r.data || [];
            const e = box.querySelector('.empty-state');
            if (!rows.length) { if (e) e.textContent = '暂无护理文书'; return; }
            if (e) e.outerHTML = '';
            const TYPE = { admission_assessment: '入院评估', nursing_plan: '护理计划', health_edu: '健康宣教', transfer_record: '转科记录', other: '其他' };
            const html = rows.map(x => `<div class="ndoc-item">
                <span class="ndoc-type-tag">${Format.escape(TYPE[x.doc_type] || x.doc_type)}</span>
                <span class="ndoc-title" title="${Format.escape(x.content)}">${Format.escape(x.title)}</span>
                <span class="ndoc-meta">${Format.escape((x.created_at || '').slice(0, 16))} · ${Format.escape(x.recorder_name || '')}</span>
                <button class="btn btn-small" onclick="NurseBedPanel.docView(${x.id})">查看</button>
                <button class="btn btn-small btn-danger" onclick="NurseBedPanel.docDel(${x.id})">删除</button>
            </div>`).join('');
            box.insertAdjacentHTML('beforeend', '<div class="ndoc-list">' + html + '</div>');
        }).catch(() => { const e = box.querySelector('.empty-state'); if (e) e.textContent = '加载失败'; });
    },

    docAdd() { this.docForm(null); },
    docForm(id) {
        const pid = this.patient.id;
        const content = `<form id="doc-form" class="form-grid">
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
            <input type="hidden" name="patient_id" value="${pid}">
            ${id ? '<input type="hidden" name="id" value="' + id + '">' : ''}
        </form>`;
        const overlay = Dom.modal('常规护理文书', content, [
            { text: '保存', type: 'primary', onclick: async function () {
                const form = document.getElementById('doc-form');
                const data = {};
                new FormData(form).forEach((v, k) => data[k] = v);
                if (!data.title.trim() || !data.content.trim()) { Dom.toast('请填写标题与内容', 'error'); return; }
                try {
                    await AjaxLoader.api('nurse/nursing-docs', { method: 'POST', data: Object.assign(data, { action: 'save' }) });
                    Dom.toast('已保存', 'success');
                    overlay.remove();
                    NurseBedPanel.refresh();
                } catch (e) { /* 已提示 */ }
            } },
            { text: '取消', type: 'default' }
        ]);
    },

    docView(id) {
        AjaxLoader.api('nurse/nursing-docs?action=list&patient_id=' + this.patient.id, { silent: true }).then(r => {
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
    },

    async docDel(id) {
        Dom.confirm('删除护理文书', '确认删除该文书？', async function () {
                await AjaxLoader.api('nurse/nursing-docs', { method: 'POST', data: { action: 'delete', id } });
                Dom.toast('已删除', 'success');
                NurseBedPanel.refresh();
            }, { danger: true, okText: '删除' });
    },

    /* ================= 出科出院 ================= */
    loadWard(box) {
        const p = this.patient;
        box.innerHTML = `<div class="nurse-op-actions">
            <button class="btn btn-primary" onclick="NurseBedPanel.transferWard()">转科</button>
            <button class="btn btn-danger" onclick="NurseBedPanel.doDischarge()">办理出院</button>
            <button class="btn" onclick="NurseBedPanel.changeBed()">迁床</button>
            <button class="btn" onclick="NurseBedPanel.chargeCheck()">费用核对</button>
            <button class="btn" onclick="NurseBedPanel.chargeAdd()">费用补录</button>
        </div>
        <div class="kv-grid">
            <div class="kv-item"><b>当前床位</b><span>${Format.escape((p.bed_no || '') + ' 床')}</span></div>
            <div class="kv-item"><b>病区</b><span>${Format.escape(p.ward_name || '-')}</span></div>
            <div class="kv-item"><b>入院时间</b><span>${Format.escape((p.admission_date || '').slice(0, 10))}</span></div>
            <div class="kv-item"><b>状态</b><span>在院</span></div>
        </div>`;
    },

    /* 迁床: 本病区换床 (选择空闲床位, 自动生成临时迁床医嘱) */
    changeBed() {
        const self = this;
        const pid = this.patient.id;
        AjaxLoader.api('patients/beds?ward_id=' + (self.patient.ward_id || ''), { silent: true }).then(br => {
            const beds = br.data || [];
            const freeBeds = beds.filter(b => !b.patient_id && b.status !== 'occupied');
            if (!freeBeds.length) { Dom.toast('本病区暂无空闲床位', 'error'); return; }
            const bedOpts = freeBeds.map(b => `<option value="${b.id}">${Format.escape(b.bed_no)} 床</option>`).join('');
            const content = `<form id="change-bed-form" class="form-grid">
                <div class="form-group"><label class="form-label">目标床位</label>
                    <select id="cb-bed" class="form-control"><option value="">请选择空闲床位</option>${bedOpts}</select></div>
                <div class="form-group colspan-2"><p class="fs-12 text-muted">迁床后将自动生成一条临时「迁床」医嘱。</p></div>
            </form>`;
            const overlay = Dom.modal('迁床 - ' + Format.escape(self.patient.patient_name || ''), content, [
                { text: '确认迁床', type: 'primary', onclick: async function () {
                    const bedId = document.getElementById('cb-bed').value;
                    if (!bedId) { Dom.toast('请选择目标床位', 'error'); return; }
                    try {
                        const r = await AjaxLoader.api('nurse/transfer-bed', { method: 'POST', data: { patient_id: pid, target_bed_id: bedId } });
                        Dom.toast(r.message || '已迁床', 'success');
                        overlay.remove();
                        self.close();
                        if (typeof NurseStation.refresh === 'function') NurseStation.refresh();
                    } catch (e) { /* 已提示 */ }
                } },
                { text: '取消', type: 'default' }
            ]);
        }).catch(() => { Dom.toast('床位加载失败', 'error'); });
    },

    /* 转科: 从本科室转出到其他科室 (医嘱全停 + 费用核对 + 患者离科) */
    transferWard() {
        const self = this;
        const pid = this.patient.id;
        AjaxLoader.api('admin/ward-list', { silent: true }).then(wr => {
            const wards = (wr.data || []).filter(w => w.is_active == 1 && String(w.id) !== String(self.patient.ward_id));
            if (!wards.length) { Dom.toast('暂无其他科室可转', 'error'); return; }
            const wardOpts = wards.map(w => `<option value="${w.id}">${Format.escape(w.name)}</option>`).join('');
            const content = `<form id="trans-ward-form" class="form-grid">
                <div class="form-group"><label class="form-label">目标科室 <span class="text-danger">*</span></label>
                    <select id="tw-ward" class="form-control"><option value="">请选择目标科室</option>${wardOpts}</select></div>
                <div class="form-group colspan-2"><p class="fs-12 text-muted">转科将从本科室离开：需医嘱已全部停止、费用核对后办理。</p></div>
            </form>`;
            const overlay = Dom.modal('转科 - ' + Format.escape(self.patient.patient_name || ''), content, [
                { text: '下一步', type: 'primary', onclick: async function () {
                    const wardId = document.getElementById('tw-ward').value;
                    if (!wardId) { Dom.toast('请选择目标科室', 'error'); return; }
                    overlay.remove();
                    // 费用核对, 确认后转科
                    self.chargeCheckBefore(function () {
                        AjaxLoader.api('nurse/leave-ward', { method: 'POST', data: { patient_id: pid, mode: 'transfer', target_ward_id: wardId } }).then(function (r) {
                            Dom.toast(r.message || '已转科', 'success');
                            self.close();
                            if (typeof NurseStation.refresh === 'function') NurseStation.refresh();
                        }).catch(function () { /* 已提示 */ });
                    });
                } },
                { text: '取消', type: 'default' }
            ]);
        }).catch(() => { Dom.toast('科室加载失败', 'error'); });
    },

    doDischarge() {
        // 出院 = 移出科室: 医嘱须已全停 + 费用核对 → 确认后办理离科
        const self = this;
        const pid = this.patient.id;
        const p = this.patient;
        const content = `<div class="kv-grid mb-10">
            <div class="kv-item"><b>患者</b><span>${Format.escape(p.patient_name || '')}</span></div>
            <div class="kv-item"><b>床号</b><span>${Format.escape(p.bed_no || '-')} 床</span></div>
            <div class="kv-item"><b>办理方式</b><span>出院</span></div>
        </div>
        <p class="fs-12 text-muted" style="line-height:1.8">出院将从本科室离开：需医嘱已全部停止、费用核对后办理；办理完成后本床位将自动释放。</p>`;
        const overlay = Dom.modal('办理出院 - ' + Format.escape(p.patient_name || ''), content, [
            { text: '费用核对并出院', type: 'primary', onclick: async function () {
                overlay.remove();
                // 费用核对, 确认后办理出院
                self.chargeCheckBefore(function () {
                    AjaxLoader.api('nurse/leave-ward', { method: 'POST', data: { patient_id: pid, mode: 'discharge' } }).then(function (r) {
                        Dom.toast(r.message || '已办理出院', 'success');
                        self.close();
                        if (typeof NurseStation.refresh === 'function') NurseStation.refresh();
                    }).catch(function (e) { /* 已提示: 未停医嘱/未核对等 */ });
                });
            } },
            { text: '取消', type: 'default' }
        ]);
    },

    /* ================= 费用核对/补录 ================= */
    /* 费用核对弹窗 (转科/出院前调用; onConfirm 在确认后执行) */
    chargeCheckBefore(onConfirm) {
        const pid = this.patient.id;
        const p = this.patient;
        AjaxLoader.api('nurse/charges?action=list&patient_id=' + pid, { silent: true }).then(r => {
            const d = r.data || {};
            const groups = d.groups || {};
            const TYPE_KEYS = ['care', 'medical', 'drug', 'material', 'other'];
            const TYPE_LABEL = { care: '护理', medical: '医疗', drug: '药品', material: '耗材', other: '其他' };
            const TABS = [];
            const PANES = [];
            let totalAmount = 0;
            TYPE_KEYS.forEach(function (tk, ti) {
                const g = groups[tk] || { label: TYPE_LABEL[tk] || tk, items: [], amount: 0 };
                totalAmount += g.amount || 0;
                TABS.push(`<button type="button" class="nurse-op-tab${ti === 0 ? ' active' : ''}" data-ctab="${tk}" onclick="NurseBedPanel.chargeTab('${tk}')">${Format.escape(g.label)}（${(g.items || []).length}）</button>`);
                const rows = (g.items || []).map(function (it) {
                    return `<tr>
                        <td>${Format.escape(it.item_name)}</td>
                        <td>${Format.escape(String(it.qty))}</td>
                        <td>¥${Format.escape(Number(it.price).toFixed(2))}</td>
                        <td>¥${Format.escape(Number(it.amount).toFixed(2))}</td>
                        <td>${it.source === 'manual' ? '<span class="badge badge-info">补录</span>' : '<span class="fs-12 text-muted">自动</span>'}</td>
                        <td>${it.source === 'manual' ? `<button class="btn btn-small btn-danger" onclick="NurseBedPanel.chargeDel(${it.id})">删除</button>` : ''}</td>
                    </tr>`;
                }).join('');
                PANES.push(`<div class="charge-pane${ti === 0 ? '' : ' hidden'}" data-cpane="${tk}">
                    <div style="overflow-x:auto"><table class="table"><thead><tr><th>项目</th><th>数量</th><th>单价</th><th>金额</th><th>来源</th><th>操作</th></tr></thead><tbody>${rows || '<tr><td colspan="6"><div class="empty-state">暂无费用</div></td></tr>'}</tbody></table></div>
                </div>`);
            });
            const content = `<div class="charge-check">
                <div class="charge-sum">费用合计：<b>¥${Number(d.total || totalAmount).toFixed(2)}</b><span class="fs-12 text-muted">（预交 ¥${Format.escape(Number(p.prepay_fee || 0).toFixed(2))}）</span></div>
                <div class="nurse-op-tabs" id="nbp-charge-tabs">${TABS.join('')}</div>
                ${PANES.join('')}
            </div>`;
            const overlay = Dom.modal('费用核对 - ' + Format.escape(p.patient_name || ''), content, [
                { text: '确认无误，继续', type: 'primary', onclick: function () {
                    overlay.remove();
                    if (typeof onConfirm === 'function') onConfirm();
                } },
                { text: '返回', type: 'default' }
            ]);
        }).catch(() => { Dom.toast('费用加载失败', 'error'); });
    },

    /* 费用核对 tab 切换 */
    chargeTab(tk) {
        const tabs = document.querySelectorAll('#nbp-charge-tabs .nurse-op-tab');
        tabs.forEach(function (t) { t.classList.toggle('active', t.dataset.ctab === tk); });
        document.querySelectorAll('.charge-pane').forEach(function (pn) {
            pn.classList.toggle('hidden', pn.dataset.cpane !== tk);
        });
    },

    /* 费用补录弹窗 */
    chargeAdd() {
        const pid = this.patient.id;
        const p = this.patient;
        const content = `<form id="charge-form" class="form-grid">
            <div class="form-group"><label class="form-label">费用类型</label>
                <select name="charge_type" class="form-control">
                    <option value="material">耗材</option><option value="care">护理</option>
                    <option value="medical">医疗</option><option value="drug">药品</option><option value="other">其他</option>
                </select></div>
            <div class="form-group"><label class="form-label">项目名称 <span class="text-danger">*</span></label>
                <input name="item_name" class="form-control" placeholder="如: 一次性注射器 / 纱布"></div>
            <div class="form-group"><label class="form-label">数量</label><input name="qty" type="number" class="form-control" value="1"></div>
            <div class="form-group"><label class="form-label">单价(元)</label><input name="price" type="number" step="0.01" class="form-control"></div>
            <div class="form-group colspan-2"><label class="form-label">备注</label>
                <input name="remark" class="form-control" placeholder="选填"></div>
            <input type="hidden" name="patient_id" value="${pid}">
        </form>`;
        const overlay = Dom.modal('费用补录 - ' + Format.escape(p.patient_name || ''), content, [
            { text: '保存', type: 'primary', onclick: async function () {
                const form = document.getElementById('charge-form');
                const data = {};
                new FormData(form).forEach((v, k) => data[k] = v);
                if (!data.item_name.trim()) { Dom.toast('请填写费用项目名称', 'error'); return; }
                try {
                    await AjaxLoader.api('nurse/charges', { method: 'POST', data: Object.assign(data, { action: 'add' }) });
                    Dom.toast('费用已补录', 'success');
                    overlay.remove();
                    NurseBedPanel.refresh();
                } catch (e) { /* 已提示 */ }
            } },
            { text: '取消', type: 'default' }
        ]);
    },

    async chargeDel(id) {
        Dom.confirm('删除补录费用', '确认删除该条补录费用？', async function () {
                await AjaxLoader.api('nurse/charges', { method: 'POST', data: { action: 'delete', id } });
                Dom.toast('已删除', 'success');
                NurseBedPanel.refresh();
            }, { danger: true, okText: '删除' });
    },

    /* 费用核对按钮 (出科出院 tab 内) */
    chargeCheck() {
        this.chargeCheckBefore(function () {
            Dom.toast('费用已核对', 'success');
        });
    }
};
</script>
