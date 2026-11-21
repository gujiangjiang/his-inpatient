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
        this.patient = patient;
        this.activeTab = 'orders';
        const p = patient;
        const level = String(p.nursing_level || '3');
        const roman = { special: '特', '1': 'Ⅰ', '2': 'Ⅱ', '3': 'Ⅲ' }[level] || 'Ⅲ';
        const head = `
            <div class="kv-grid mb-10">
                <div class="kv-item"><b>床号</b><span>${Format.escape(roman + ' | ' + (p.bed_no || '-'))}</span></div>
                <div class="kv-item"><b>姓名</b><span>${Format.escape(p.patient_name || p.name || '')}</span></div>
                <div class="kv-item"><b>性别</b><span>${Format.gender(p.gender)}</span></div>
                <div class="kv-item"><b>年龄</b><span>${p.age ? p.age + ' 岁' : '-'}</span></div>
                <div class="kv-item"><b>住院号</b><span>${Format.escape(p.admission_no || '-')}</span></div>
                <div class="kv-item"><b>入院</b><span>${Format.escape((p.admission_date || '').slice(0, 10) || '-')}</span></div>
                <div class="kv-item"><b>费别</b><span>${Format.escape(p.fee_type || '-')}</span></div>
                <div class="kv-item"><b>诊断</b><span>${Format.escape(p.admission_diagnosis || '-')}</span></div>
            </div>
            <div class="nurse-op-tabs" id="nbp-tabs">
                <button type="button" class="nurse-op-tab active" data-tab="orders" onclick="NurseBedPanel.switch('orders')">医嘱</button>
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
        this.loadTab('orders');
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
        if (tab === 'orders') this.loadOrders(box);
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

    /* ================= 医嘱 ================= */
    loadOrders(box) {
        const pid = this.patient.id;
        box.innerHTML = '<div class="empty-state">加载中…</div>';
        AjaxLoader.api('nurse/order-list?patient_id=' + pid, { silent: true }).then(r => {
            const rows = r.data || [];
            const pendingN = rows.filter(o => o.status === 'pending').length;
            const catLabel = c => c === 'medication' ? '药品' : '医嘱';
            const actHtml = o => {
                if (o.status === 'invalid') return '<span class="fs-12 text-muted">已作废</span>';
                if (o.status === 'invalid_pending') return `<button class="btn btn-small btn-danger" onclick="NurseBedPanel.confirmOrder(${o.id},'invalid')">确认作废</button>`;
                if (o.status === 'stop_pending') return `<button class="btn btn-small btn-danger" onclick="NurseBedPanel.confirmOrder(${o.id},'stopped')">确认停止</button>`;
                if (o.status === 'pending') return `<button class="btn btn-small btn-success" onclick="NurseBedPanel.verifyOrder(${o.id})">核查</button>`;
                if (o.category === 'medication' && !o.dispensed_id) return '<span class="fs-12 text-muted">待发药后执行</span>';
                return `<button class="btn btn-small btn-success" onclick="NurseBedPanel.executeOrder(${o.id})">执行</button>`;
            };
            const rowCls = o => o.status === 'invalid' ? 'os-txt-invalid'
                : (o.status === 'invalid_pending' ? 'os-txt-invalid_pending' : (o.status === 'stop_pending' ? 'os-txt-stop_pending' : ''));
            const body = rows.length
                ? rows.map(o => `<tr class="${rowCls(o)}">
                    <td>${Format.escape(o.content)}</td>
                    <td>${catLabel(o.category)}</td>
                    <td>${Format.escape([o.usage_name, o.freq_code].filter(Boolean).join(' · '))}</td>
                    <td><span class="badge ${Format.orderStatusClass(o.status)}">${Format.orderStatus(o.status)}</span></td>
                    <td>${actHtml(o)}</td></tr>`).join('')
                : '<tr><td colspan="5"><div class="empty-state">暂无医嘱</div></td></tr>';
            const toolbar = pendingN > 0
                ? `<div class="nurse-op-actions">
                    <button class="btn btn-primary" onclick="NurseBedPanel.verifyAll()">一键核对（${pendingN} 条）</button>
                    <span class="fs-12 text-muted">全部待核查医嘱一键核对</span>
                  </div>`
                : '';
            box.innerHTML = toolbar + `<table class="table"><thead><tr><th>医嘱</th><th>类型</th><th>途径 · 频次</th><th>状态</th><th>操作</th></tr></thead><tbody>${body}</tbody></table>`;
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
                age: p.age ? p.age + ' 岁' : '',
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
            // 打印预览: iframe 以 100% 原尺寸渲染, transform 等比缩放显示
            const overlay = Dom.modal('病历预览 - ' + Format.escape(d.title || ''), '<div class="cf-preview-scale" id="nbp-preview-scale"><iframe class="cf-preview-frame" id="nbp-preview-frame" scrolling="no"></iframe></div>', [
                { text: '打印', type: 'primary', onclick: function () {
                    const win = window.open('', '_blank', 'width=994,height=1323');
                    if (!win) { Dom.toast('浏览器拦截了打印窗口, 请允许弹出窗口', 'error'); return; }
                    win.document.write(html);
                    win.document.close();
                } },
                { text: '关闭', type: 'default' }
            ]);
            overlay.querySelector('.modal').classList.add('modal-cf-preview');
            const wrap = overlay.querySelector('#nbp-preview-scale');
            const frame = overlay.querySelector('#nbp-preview-frame');
            const fit = () => {
                const avail = wrap.clientWidth || 820;
                const s = Math.min(1, avail / 794);
                const doc = frame.contentDocument;
                const h = doc ? Math.max(doc.documentElement.scrollHeight, 2280) : 2280;
                frame.style.width = '794px';
                frame.style.height = h + 'px';
                frame.style.transform = 'scale(' + s + ')';
                frame.style.transformOrigin = 'top left';
                wrap.style.height = Math.ceil(h * s) + 'px';
            };
            frame.addEventListener('load', fit);
            frame.srcdoc = html;
            setTimeout(fit, 120);
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
                <td><span class="badge ${x.status === 'reported' ? 'badge-success' : (x.status === 'pending' ? 'badge-pending' : 'badge-info')}">${LAB[x.status] || Format.escape(x.status)}</span></td>
                <td>${x.status === 'pending' ? `<button class="btn btn-small btn-success" onclick="NurseBedPanel.collectLab(${x.id})">采集</button>` : '<span class="fs-12 text-muted">-</span>'}</td>
            </tr>`).join('')
                : '<tr><td colspan="5"><div class="empty-state">暂无检验</div></td></tr>';
            const examRows = exams.length ? exams.map(x => `<tr>
                <td>${Format.escape(x.exam_no || x.id)}</td><td>${Format.escape(x.exam_type)}</td>
                <td>${Format.escape(x.exec_dept || '')}</td>
                <td><span class="badge ${x.status === 'reported' ? 'badge-success' : 'badge-pending'}">${EXM[x.status] || Format.escape(x.status)}</span></td>
                <td>${x.status === 'reported' ? '<button class="btn btn-small" onclick="NurseBedPanel.viewExam(' + x.id + ')">详情</button>' : '<span class="fs-12 text-muted">-</span>'}</td>
            </tr>`).join('')
                : '<tr><td colspan="5"><div class="empty-state">暂无检查</div></td></tr>';
            box.innerHTML = `
                <div class="card-header" style="border-bottom:1px solid #eef2f7">检验申请</div>
                <div style="overflow-x:auto"><table class="table"><thead><tr><th>单号</th><th>项目</th><th>标本</th><th>状态</th><th>操作</th></tr></thead><tbody>${labRows}</tbody></table></div>
                <div class="card-header" style="border-bottom:1px solid #eef2f7;margin-top:14px">检查申请</div>
                <div style="overflow-x:auto"><table class="table"><thead><tr><th>单号</th><th>项目</th><th>执行科室</th><th>状态</th><th>操作</th></tr></thead><tbody>${examRows}</tbody></table></div>`;
        });
    },

    async collectLab(id) {
        await AjaxLoader.api('lab/collect', { method: 'POST', data: { id } });
        Dom.toast('已采集', 'success');
        this.refresh();
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

    /* ================= 体温单 ================= */
    loadTemperature(box) {
        const pid = this.patient.id;
        box.innerHTML = `<div class="temp-date-nav">
            <span class="td-date">体温单</span>
            <button class="btn btn-small btn-primary" onclick="NurseBedPanel.tempAdd()">录入</button>
        </div><div class="empty-state">加载中…</div>`;
        AjaxLoader.api('nurse/temperature?action=list&patient_id=' + pid, { silent: true }).then(r => {
            const rows = r.data || [];
            if (!rows.length) { box.querySelector('.empty-state').textContent = '暂无体温记录，点击「录入」添加'; return; }
            // 按日期分组展示 (日期 + 各时点)
            const byDate = {};
            rows.forEach(x => { (byDate[x.record_date] = byDate[x.record_date] || []).push(x); });
            const dates = Object.keys(byDate).sort().reverse();
            const SLOTS = ['06:00', '10:00', '14:00', '18:00', '22:00'];
            const html = dates.map(d => {
                const day = byDate[d];
                const cells = SLOTS.map(s => {
                    const hit = day.find(x => x.time_slot === s);
                    return hit
                        ? `<td class="td-act td-val" onclick="NurseBedPanel.tempEdit(${hit.id})" title="点击修改">
                             ${hit.temperature != null ? Format.escape(String(hit.temperature)) + '°' : ''}
                             ${hit.pulse != null ? '<br><span class="fs-11">脉 ' + Format.escape(String(hit.pulse)) + '</span>' : ''}
                           </td>`
                        : `<td class="td-empty">-</td>`;
                }).join('');
                return `<tr><td class="td-time">${Format.escape(d)}</td>${cells}</tr>`;
            }).join('');
            const container = box.querySelector('.empty-state');
            if (container) container.outerHTML = '';
            box.insertAdjacentHTML('beforeend', `<div class="temp-grid-wrap">
                <table class="temp-grid">
                    <thead><tr><th>日期</th><th>06:00</th><th>10:00</th><th>14:00</th><th>18:00</th><th>22:00</th></tr></thead>
                    <tbody>${html}</tbody></table></div>`);
        }).catch(() => { const e = box.querySelector('.empty-state'); if (e) e.textContent = '加载失败'; });
    },

    tempAdd() { this.tempForm(null); },
    tempEdit(id) { this.tempForm(id); },
    tempForm(id) {
        const pid = this.patient.id;
        const content = `<form id="temp-form" class="form-grid">
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
            <input type="hidden" name="patient_id" value="${pid}">
            ${id ? '<input type="hidden" name="id" value="' + id + '">' : ''}
        </form>`;
        const overlay = Dom.modal(id ? '修改体温记录' : '录入体温单', content, [
            { text: '保存', type: 'primary', onclick: async function () {
                const form = document.getElementById('temp-form');
                const data = {};
                new FormData(form).forEach((v, k) => data[k] = v);
                try {
                    await AjaxLoader.api('nurse/temperature', { method: 'POST', data: Object.assign(data, { action: 'save' }) });
                    Dom.toast('已保存', 'success');
                    overlay.remove();
                    NurseBedPanel.refresh();
                } catch (e) { /* 已提示 */ }
            } },
            { text: '取消', type: 'default' }
        ]);
    },

    /* ================= 护理记录单 ================= */
    loadNotes(box) {
        const pid = this.patient.id;
        box.innerHTML = `<div class="nurse-op-actions">
            <button class="btn btn-primary" onclick="NurseBedPanel.noteAdd()">新增记录</button>
        </div><div class="empty-state">加载中…</div>`;
        AjaxLoader.api('nurse/nursing-notes?action=list&patient_id=' + pid, { silent: true }).then(r => {
            const rows = r.data || [];
            const e = box.querySelector('.empty-state');
            if (!rows.length) { if (e) e.textContent = '暂无护理记录'; return; }
            if (e) e.outerHTML = '';
            const CAT = { observation: '病情观察', measure: '测量', care: '护理措施', medication: '给药', transfer: '转科', other: '其他' };
            const html = rows.map(x => `<div class="nnote-row">
                <span class="nnote-time">${Format.escape((x.record_time || '').slice(0, 16))}</span>
                <span class="nnote-cat"><span class="badge badge-info">${Format.escape(CAT[x.category] || x.category)}</span></span>
                <span class="nnote-content">${Format.escape(x.content)}
                    <div class="nnote-meta">${x.temperature != null ? '体温 ' + Format.escape(String(x.temperature)) + '° ' : ''}${x.pulse != null ? '脉搏 ' + Format.escape(String(x.pulse)) + ' ' : ''}${x.bp_systolic != null ? '血压 ' + Format.escape(String(x.bp_systolic)) + '/' + Format.escape(String(x.bp_diastolic)) + ' ' : ''}${x.intake_ml != null ? '入量 ' + Format.escape(String(x.intake_ml)) + 'ml ' : ''}${x.output_ml != null ? '出量 ' + Format.escape(String(x.output_ml)) + 'ml ' : ''}记录人：${Format.escape(x.recorder_name || '')}</div>
                </span>
                <button type="button" class="nnote-del" title="删除" onclick="NurseBedPanel.noteDel(${x.id})">×</button>
            </div>`).join('');
            box.insertAdjacentHTML('beforeend', '<div class="nnote-list">' + html + '</div>');
        }).catch(() => { const e = box.querySelector('.empty-state'); if (e) e.textContent = '加载失败'; });
    },

    noteAdd() { this.noteForm(null); },
    noteForm(id) {
        const pid = this.patient.id;
        const content = `<form id="note-form" class="nnote-form">
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
            <input type="hidden" name="patient_id" value="${pid}">
            ${id ? '<input type="hidden" name="id" value="' + id + '">' : ''}
        </form>`;
        const overlay = Dom.modal('护理记录单', content, [
            { text: '保存', type: 'primary', onclick: async function () {
                const form = document.getElementById('note-form');
                const data = {};
                new FormData(form).forEach((v, k) => data[k] = v);
                if (!data.content.trim()) { Dom.toast('请输入记录内容', 'error'); return; }
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
            <button class="btn btn-primary" onclick="NurseBedPanel.transferBed()">转床</button>
            <button class="btn btn-danger" onclick="NurseBedPanel.doDischarge()">办理出院</button>
        </div>
        <div class="kv-grid">
            <div class="kv-item"><b>当前床位</b><span>${Format.escape((p.bed_no || '') + ' 床')}</span></div>
            <div class="kv-item"><b>病区</b><span>${Format.escape(p.ward_name || '-')}</span></div>
            <div class="kv-item"><b>入院时间</b><span>${Format.escape((p.admission_date || '').slice(0, 10))}</span></div>
            <div class="kv-item"><b>状态</b><span>在院</span></div>
        </div>`;
    },

    /* 转床: 选择目标病区/床位, 更新患者床位与床位占用 */
    transferBed() {
        const self = this;
        const pid = this.patient.id;
        Promise.all([
            AjaxLoader.api('admin/ward-list', { silent: true }),
            AjaxLoader.api('patients/beds', { silent: true })
        ]).then(([wr, br]) => {
            const wards = (wr.data || []).filter(w => w.is_active == 1);
            const beds = br.data || [];
            const wardOpts = wards.map(w => `<option value="${w.id}">${Format.escape(w.name)}</option>`).join('');
            const content = `<form id="transfer-form" class="form-grid">
                <div class="form-group"><label class="form-label">目标病区</label>
                    <select id="trans-ward" class="form-control">
                        <option value="">请选择病区</option>${wardOpts}</select></div>
                <div class="form-group"><label class="form-label">目标床位</label>
                    <select id="trans-bed" class="form-control"><option value="">请先选择病区</option></select></div>
            </form>`;
            const overlay = Dom.modal('转床 - ' + Format.escape(self.patient.patient_name || ''), content, [
                { text: '确认转床', type: 'primary', onclick: async function () {
                    const wardId = document.getElementById('trans-ward').value;
                    const bedId = document.getElementById('trans-bed').value;
                    if (!wardId || !bedId) { Dom.toast('请选择目标病区和床位', 'error'); return; }
                    const bed = beds.find(b => Number(b.id) === Number(bedId));
                    if (!bed || bed.status === 'occupied') { Dom.toast('该床位已被占用', 'error'); return; }
                    try {
                        const r = await AjaxLoader.api('nurse/transfer-bed', { method: 'POST', data: { patient_id: pid, target_bed_id: bedId } });
                        Dom.toast(r.message || '已转床', 'success');
                        overlay.remove();
                        self.close();
                        if (typeof NurseStation.refresh === 'function') NurseStation.refresh();
                    } catch (e) { /* 已提示 */ }
                } },
                { text: '取消', type: 'default' }
            ]);
            // 病区-床位联动
            window._nurseTransBeds = beds;
            window._nurseTransWards = wards;
            overlay.querySelector('#trans-ward').addEventListener('change', function () {
                const wId = this.value;
                const sel = overlay.querySelector('#trans-bed');
                const list = (window._nurseTransBeds || []).filter(b => String(b.ward_id) === String(wId) && b.status !== 'occupied');
                sel.innerHTML = '<option value="">请选择床位</option>' + list.map(b => `<option value="${b.id}">${Format.escape(b.bed_no)} 床</option>`).join('');
            });
        }).catch(() => {});
    },

    doDischarge() {
        // 复用医生出院办理弹窗逻辑不可行 (需医嘱上下文); 护士端简单出院: 提示由医生开具出院医嘱
        Dom.modal('办理出院', `
            <p class="fs-13" style="line-height:1.9">
                出院需由主管医生开具「出院医嘱」并完成病案首页填写。<br>
                护士确认床位是否已由医生标记今日出院；办理完成后本床位将自动释放。
            </p>
            <div class="empty-state" style="padding:14px 0">
                当前为护士工作站：出院办理请在医嘱工作站由医生完成，或联系主管医生。
            </div>`,
            [{ text: '知道了', type: 'primary' }]);
    }
};
</script>