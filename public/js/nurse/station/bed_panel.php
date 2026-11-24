<?php /* 床位卡片操作面板片段 (被 nurse/station.php 包含): 集中 查医嘱/查病历/查检验检查/摆药/护理文书/出科出院 */
require_once __DIR__ . '/../../../icons/icon.php'; ?>
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
        this._tempStart = '';
        this._tempDemo = false;
        this._tempData = null;
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
                <button type="button" class="nurse-op-tab" data-tab="medicine" onclick="NurseBedPanel.switch('medicine')">摆药</button>
                <button type="button" class="nurse-op-tab" data-tab="lab" onclick="NurseBedPanel.switch('lab')">检验</button>
                <button type="button" class="nurse-op-tab" data-tab="exam" onclick="NurseBedPanel.switch('exam')">检查</button>
                <button type="button" class="nurse-op-tab" data-tab="records" onclick="NurseBedPanel.switch('records')">病历</button>
                <button type="button" class="nurse-op-tab" data-tab="temperature" onclick="NurseBedPanel.switch('temperature')">体温单</button>
                <button type="button" class="nurse-op-tab" data-tab="notes" onclick="NurseBedPanel.switch('notes')">护理记录</button>
                <button type="button" class="nurse-op-tab" data-tab="docs" onclick="NurseBedPanel.switch('docs')">护理文书</button>
                <button type="button" class="nurse-op-tab" data-tab="ward" onclick="NurseBedPanel.switch('ward')">出科出院</button>
            </div>
            <div class="nurse-op-body" id="nbp-body"><div class="empty-state">加载中…</div></div>`;
        const overlay = Dom.modal('床位操作 - ' + (p.patient_name || p.name || ''), head, []);
        const modal = overlay.querySelector('.modal');
        modal.classList.add('modal-lg');
        /* 底部按钮栏: 左侧为各 tab 操作按钮, 右侧固定关闭; 由 Dom.modal footer 结构承载 */
        const footer = overlay.querySelector('.modal-footer') || document.createElement('div');
        if (!overlay.querySelector('.modal-footer')) {
            footer.className = 'modal-footer';
            modal.appendChild(footer);
        }
        footer.className += ' nbp-footer';
        footer.innerHTML = '<div class="nbp-bar-left" id="nbp-bar-left"></div>'
            + '<div class="nbp-bar-right"><button type="button" class="btn" onclick="NurseBedPanel.close()">关闭</button></div>';
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
        else if (tab === 'records') { this.setBottomBar(''); this.loadRecords(box); }
        else if (tab === 'lab') this.loadLab(box);
        else if (tab === 'exam') this.loadExam(box);
        else if (tab === 'medicine') this.loadMedicine(box);
        else if (tab === 'temperature') this.loadTemperature(box);
        else if (tab === 'notes') this.loadNotes(box);
        else if (tab === 'docs') this.loadDocs(box);
        else if (tab === 'ward') this.loadWard(box);
    },

    close() {
        if (this._overlay && this._overlay.parentNode) this._overlay.remove();
    },

    /* 底部按钮栏: 左侧各 tab 操作按钮 (统一收敛到 modal footer 左区, 右侧固定关闭) */
    setBottomBar(html) {
        const bar = document.getElementById('nbp-bar-left');
        if (bar) bar.innerHTML = html || '';
    },

    refresh() {
        this.loadTab(this.activeTab);
    },

    /* ================= 患者信息 (基本信息 + 身高体重补填 + 腕带预留) ================= */
    loadInfo(box) {
        const pid = this.patient.id;
        const p = this.patient;
        box.innerHTML = '<div class="empty-state">加载中…</div>';
        this.setBottomBar(`
            <button class="btn" onclick="NurseBedPanel.infoEdit()">修改个人信息</button>
            <button class="btn" onclick="NurseBedPanel.wristbandPreview()">腕带打印</button>`);
        AjaxLoader.api('nurse/patient-info?action=get&patient_id=' + pid, { silent: true }).then(r => {
            const d = r.data || {};
            const age = Format.ageClinical(d.birth_date);
            const roman = { special: '特', '1': 'Ⅰ', '2': 'Ⅱ', '3': 'Ⅲ' }[String(d.nursing_level || '3')] || 'Ⅲ';
            const infoHtml = `<div class="kv-grid">
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
            /* 组合医嘱按组展示: 根医嘱 + 子医嘱合并为一行(组), 整组一起核查/停止/作废;
               单条医嘱(无 parent_id)自成一组。 */
            const groups = [];
            const subsByParent = {};
            rows.forEach(o => {
                if (o.parent_id) (subsByParent[o.parent_id] = subsByParent[o.parent_id] || []).push(o);
                else groups.push(o);
            });
            groups.forEach(g => { g.sub_orders = subsByParent[g.id] || []; });
            /* 待核查组数 = 状态为 pending 的根医嘱数 (子医嘱随根级联, 不单独计) */
            const pendingN = groups.filter(o => o.status === 'pending').length;
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
            const groupHtml = g => {
                const root = g;
                const subs = (g.sub_orders || []).map(s => `<div class="nbp-sub-order">${Format.escape(s.content)}${s.dosage ? ' ' + Format.escape(s.dosage) : ''}</div>`).join('');
                const content = `${Format.escape(root.content)}${root.dosage ? ' ' + Format.escape(root.dosage) : ''}${subs ? `<div class="nbp-sub-wrap">${subs}</div>` : ''}`;
                return `<tr class="${rowCls(root)}">
                    <td>${content}</td>
                    <td>${catLabel(root.category)}</td>
                    <td>${Format.escape([root.usage_name, root.freq_code].filter(Boolean).join(' · '))}</td>
                    <td>${Format.escape(root.doctor_name || '-')}</td>
                    <td>${Format.escape(root.stopped_name || '-')}</td>
                    <td><span class="badge ${Format.orderStatusClass(root.status)}">${Format.orderStatus(root.status)}</span></td>
                    <td>${actHtml(root)}</td></tr>`;
            };
            const body = groups.length
                ? groups.map(groupHtml).join('')
                : '<tr><td colspan="7"><div class="empty-state">暂无医嘱</div></td></tr>';
            /* 底部按钮栏: 批量核对常显 (待核对组数角标), 执行单打印, 医嘱单打印 */
            this.setBottomBar(`
                <button class="btn btn-primary" onclick="NurseBedPanel.batchVerify()">批量核对${pendingN > 0 ? `<span class="nbp-badge">${pendingN}</span>` : ''}</button>
                <button class="btn" onclick="NurseBedPanel.execSheetPrint()">执行单打印</button>
                <button class="btn" onclick="NurseBedPanel.printOrderSheets()">医嘱单打印</button>`);
            box.innerHTML = Dom.scrollTable('<tr><th>医嘱</th><th>类型</th><th>途径 · 频次</th><th>开嘱医生</th><th>停嘱医生</th><th>状态</th><th>操作</th></tr>', body, { wrap: false });
        }).catch(() => { box.innerHTML = '<div class="empty-state">加载失败</div>'; });
    },

    /* 批量核对: 弹窗列出待核对医嘱 (组合医嘱整组一行), 复选框 + 表头全选, 确认后子医嘱级联核对 */
    batchVerify() {
        const pid = this.patient.id;
        const p = this.patient;
        const self = this;
        AjaxLoader.api('nurse/order-list?patient_id=' + pid, { silent: true }).then(r => {
            const rows = r.data || [];
            const subsByParent = {};
            rows.forEach(o => { if (o.parent_id) (subsByParent[o.parent_id] = subsByParent[o.parent_id] || []).push(o); });
            const pending = rows.filter(o => !o.parent_id && o.status === 'pending');
            if (!pending.length) { Dom.toast('没有待核对的医嘱', 'info'); return; }
            const trs = pending.map(o => {
                const subs = (subsByParent[o.id] || []).map(s => `<div class="nbp-sub-order">${Format.escape(s.content)}${s.dosage ? ' ' + Format.escape(s.dosage) : ''}</div>`).join('');
                const content = `${Format.escape(o.content)}${o.dosage ? ' ' + Format.escape(o.dosage) : ''}${subs ? `<div class="nbp-sub-wrap">${subs}</div>` : ''}`;
                return `<tr>
                    <td><input type="checkbox" class="nbp-verify-check" value="${o.id}" checked></td>
                    <td>${content}</td>
                    <td>${Format.escape([o.usage_name, o.freq_code].filter(Boolean).join(' · '))}</td>
                    <td>${Format.escape(o.doctor_name || '-')}</td>
                </tr>`;
            }).join('');
            const content = `<div class="nbp-exec-sheet">
                ${Dom.scrollTable('<tr><th class="nbp-check-all"><input type="checkbox" id="nbp-verify-all" checked title="全选/全不选"></th><th>医嘱</th><th>途径 · 频次</th><th>开嘱医生</th></tr>', trs)}
                <p class="fs-12 text-muted" style="margin-top:8px">共 ${pending.length} 组待核对；组合医嘱整组核对，子医嘱随根医嘱级联。</p>
            </div>`;
            const overlay = Dom.modal('批量核对 - ' + Format.escape(p.patient_name || ''), content, [
                { text: '批量核对', type: 'primary', onclick: async function () {
                    const ids = [...document.querySelectorAll('.nbp-verify-check:checked')].map(c => Number(c.value));
                    if (!ids.length) { Dom.toast('请先勾选要核对的医嘱', 'error'); return; }
                    try {
                        const res = await AjaxLoader.api('nurse/verify-batch', { method: 'POST', data: { patient_id: pid, ids } });
                        Dom.toast(res.message || '已核对', 'success');
                        overlay.remove();
                        self.refresh();
                        if (typeof NurseStation.refresh === 'function') NurseStation.refresh();
                    } catch (e) { /* 已提示 */ }
                } },
                { text: '取消', type: 'default' }
            ], { large: true });
            /* 表头复选框: 全选/全不选 */
            const all = overlay.querySelector('#nbp-verify-all');
            if (all) all.addEventListener('change', function () {
                overlay.querySelectorAll('.nbp-verify-check').forEach(c => { c.checked = all.checked; });
            });
        }).catch(() => { Dom.toast('待核对医嘱加载失败', 'error'); });
    },

    /* 执行单打印: 弹窗选择已核对药品医嘱 (含子医嘱), 记录打印留痕, 已打印灰显
       打印复用医嘱执行贴模板 (PrintLabels.medicationExecution, 60×60mm 正方形) 批量组合 */
    execSheetPrint() {
        const pid = this.patient.id;
        const p = this.patient;
        const self = this;
        AjaxLoader.api('nurse/execution-sheet-list?patient_id=' + pid, { silent: true }).then(r => {
            const items = r.data || [];
            if (!items.length) { Dom.toast('暂无已核对的药品医嘱可打印执行单', 'info'); return; }
            /* 行: 复选框 + 医嘱内容(含子医嘱) + 打印状态; 已打印灰显 */
            const rows = items.map(o => {
                const subs = (o.sub_orders || []).map(s =>
                    `<div class="nbp-sub-order">${Format.escape(s.content)}${s.dosage ? ' ' + Format.escape(s.dosage) : ''}</div>`).join('');
                const content = `${Format.escape(o.content)}${o.dosage ? ' ' + Format.escape(o.dosage) : ''}${subs ? `<div class="nbp-sub-wrap">${subs}</div>` : ''}`;
                const checked = o.printed ? '' : 'checked';
                const status = o.printed
                    ? `<span class="fs-12 text-muted" title="${Format.escape((o.printed_name || '') + ' ' + (o.printed_at || ''))}">已打印</span>`
                    : '<span class="badge badge-warning">未打印</span>';
                return `<tr class="${o.printed ? 'nbp-exec-printed' : ''}">
                    <td><input type="checkbox" class="nbp-exec-check" value="${o.id}" ${checked}></td>
                    <td>${content}</td>
                    <td>${Format.escape([o.usage_name, o.freq_code].filter(Boolean).join(' · '))}</td>
                    <td>${Format.escape(o.order_period === 'temp' ? '临时' : '长期')}</td>
                    <td>${status}</td>
                </tr>`;
            }).join('');
            const content = `<div class="nbp-exec-sheet">
                ${Dom.scrollTable('<tr><th></th><th>医嘱</th><th>途径 · 频次</th><th>类型</th><th>打印</th></tr>', rows)}
                <p class="fs-12 text-muted" style="margin-top:8px">默认勾选未打印的医嘱；已打印的可手动勾选补打。打印后自动记录留痕。</p>
            </div>`;
            const overlay = Dom.modal('执行单打印 - ' + Format.escape(p.patient_name || ''), content, [
                { text: '全部勾选', type: 'default', onclick: function () {
                    document.querySelectorAll('.nbp-exec-check').forEach(c => c.checked = true);
                } },
                { text: '一键打印执行单', type: 'primary', onclick: async function () {
                    const ids = [...document.querySelectorAll('.nbp-exec-check:checked')].map(c => Number(c.value));
                    if (!ids.length) { Dom.toast('请勾选要打印的医嘱', 'error'); return; }
                    /* 先留痕, 再组装执行贴预览 */
                    try {
                        await AjaxLoader.api('nurse/execution-sheet-print', { method: 'POST', data: { patient_id: pid, ids } });
                        self.execSheetLabels(items.filter(o => ids.indexOf(Number(o.id)) >= 0));
                        overlay.remove();
                    } catch (e) { /* 已提示 */ }
                } },
                { text: '取消', type: 'default' }
            ], { large: true });
        }).catch(() => { Dom.toast('执行单数据加载失败', 'error'); });
    },

    /* 组装执行单标签预览: 选中医嘱逐条走执行贴模板, 页面展平合并为一份标签集 */
    execSheetLabels(selected) {
        const pages = [], css = [];
        let hasAny = false;
        selected.forEach(function (o) {
            const template = PrintLabels.medicationExecution(o);
            if (!template) return;
            hasAny = true;
            if (template.css && css.indexOf(template.css) < 0) css.push(template.css);
            (template.pages || [template.html]).forEach(function (page) {
                if (page && String(page).trim()) pages.push(page);
            });
        });
        if (!hasAny || !pages.length) { Dom.toast('暂无可打印的执行单内容', 'info'); return; }
        PrintLabels.open({
            title: '医嘱执行单打印',
            previewTitle: '医嘱执行单 · 打印预览',
            paper: 'MED_LABEL',
            css: css.join('\n'),
            pages: pages
        });
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

    /* 组装医嘱单 A4 文档 (抬头与电子病历一致; hasStop: 长期单含停止时间列, 临时单无)
       组合医嘱整组显示: 根医嘱行 + 子医嘱缩进于内容列下方, 时间/用法/频次等共享根医嘱 */
    buildOrderSheetHtml(title, rows, patient, hasStop) {
        const esc = function (s) { return Format.escape(String(s == null ? '' : s)); };
        const info = PrintPreview.patientInfo(patient, title);
        const header = PrintPreview.sheetHeader(info);
        /* 按后端排序 (组根在前, 组内 group_seq) 扫描分组 */
        const groups = [];
        let cur = null;
        rows.forEach(function (o) {
            if (!o.parent_id || o.is_group_main == 1) {
                cur = { root: o, subs: [] };
                groups.push(cur);
            } else if (cur) {
                cur.subs.push(o);
            }
        });
        const groupBody = function (g) {
            const root = g.root;
            const subsHtml = g.subs.map(function (s) {
                return '<div class="os-sub">' + esc(s.content) + (s.dosage ? ' ' + esc(s.dosage) + (s.dosage_unit ? esc(s.dosage_unit) : '') : '') + '</div>';
            }).join('');
            const content = esc(root.content) + (root.dosage ? ' ' + esc(root.dosage) + (root.dosage_unit ? esc(root.dosage_unit) : '') : '')
                + (root.status === 'stopped' ? '（已停）' : '')
                + (subsHtml ? '<div class="os-sub-wrap">' + subsHtml + '</div>' : '');
            const usage = [root.usage_name, root.usage_rate].filter(Boolean).join(' ');
            const freq = [root.freq_code, root.freq_detail].filter(Boolean).join(' ');
            const start = root.start_date ? esc(String(root.start_date).slice(0, 16)) : '';
            const stop = esc(root.stop_date ? String(root.stop_date).slice(0, 16) : (root.end_date ? String(root.end_date).slice(0, 16) : ''));
            const cells = hasStop
                ? '<td class="os-time">' + start + '</td><td class="os-time">' + stop + '</td>'
                : '<td class="os-time">' + start + '</td>';
            return '<tr>' + cells
                + '<td class="os-content">' + content + '</td>'
                + '<td class="os-usage">' + esc(usage) + '</td>'
                + '<td class="os-freq">' + esc(freq) + '</td>'
                + '<td class="os-sign">' + esc(root.doctor_name || '') + '</td>'
                + (hasStop ? '<td class="os-sign">' + esc(root.stopped_name || '') + '</td>' : '')
                + '<td class="os-sign">' + esc(root.verified_name || '') + '</td>'
                + '<td class="os-sign">' + esc(root.exec_name || '') + '</td>'
                + '</tr>';
        };
        const body = groups.map(groupBody).join('');
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
            '.os-sub-wrap { margin-top: 3px; padding-left: 12px; border-left: 1.5px solid #999; }',
            '.os-sub { font-size: 9.5pt; line-height: 1.5; }',
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
            if (!docs.length) {
                box.innerHTML = Dom.scrollTable('<tr><th>文书</th><th>分类</th><th>更新时间</th><th>状态</th><th>操作</th></tr>',
                    '<tr><td colspan="5"><div class="empty-state">暂无病历文书</div></td></tr>', { wrap: false });
                return;
            }
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
            box.innerHTML = Dom.scrollTable('<tr><th>文书</th><th>分类</th><th>更新时间</th><th>状态</th><th>操作</th></tr>', rows, { wrap: false });
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

    /* ================= 检验 (独立 tab, 左下角批量打印条码) ================= */
    loadLab(box) {
        const pid = this.patient.id;
        box.innerHTML = '<div class="empty-state">加载中…</div>';
        this.setBottomBar(`<button class="btn" onclick="NurseBedPanel.batchLabBarcodePrint()">检验条码打印</button>`);
        AjaxLoader.api('lab/list?patient_id=' + pid, { silent: true }).then(r => {
            const labs = r.data || [];
            const LAB = { pending: '待采集', collected: '已采集', reported: '已报告' };
            const labRows = labs.length ? labs.map(x => `<tr>
                <td>${Format.escape(x.report_no)}</td><td>${Format.escape(x.exam_name)}</td>
                <td>${Format.escape(x.specimen_type || '')}</td>
                <td>${LabBarcode.statusHtml(x)}</td>
                <td><span class="badge ${x.status === 'reported' ? 'badge-success' : (x.status === 'pending' ? 'badge-pending' : 'badge-info')}">${LAB[x.status] || Format.escape(x.status)}</span></td>
                <td><button class="btn btn-small" onclick="NurseBedPanel.printLabBarcode(${x.id})">${LabBarcode.buttonText(x)}</button>
                    ${x.status === 'pending' ? `<button class="btn btn-small btn-success" onclick="NurseBedPanel.collectLab(${x.id})">采集</button>` : ''}</td>
            </tr>`).join('')
                : '<tr><td colspan="6"><div class="empty-state">暂无检验</div></td></tr>';
            box.innerHTML = Dom.scrollTable('<tr><th>单号</th><th>项目</th><th>标本</th><th>条码</th><th>状态</th><th>操作</th></tr>', labRows, { wrap: false });
        }).catch(() => { box.innerHTML = '<div class="empty-state">加载失败</div>'; });
    },

    /* ================= 检查 (独立 tab, 只读) ================= */
    loadExam(box) {
        const pid = this.patient.id;
        box.innerHTML = '<div class="empty-state">加载中…</div>';
        this.setBottomBar('');
        AjaxLoader.api('exam/list?patient_id=' + pid, { silent: true }).then(r => {
            const exams = r.data || [];
            const EXM = { pending: '待执行', reported: '已报告' };
            const examRows = exams.length ? exams.map(x => `<tr>
                <td>${Format.escape(x.exam_no || x.id)}</td><td>${Format.escape(x.exam_type)}</td>
                <td>${Format.escape(x.exec_dept || '')}</td>
                <td><span class="badge ${x.status === 'reported' ? 'badge-success' : 'badge-pending'}">${EXM[x.status] || Format.escape(x.status)}</span></td>
                <td>${x.status === 'reported' ? '<button class="btn btn-small" onclick="NurseBedPanel.viewExam(' + x.id + ')">详情</button>' : '<span class="fs-12 text-muted">-</span>'}</td>
            </tr>`).join('')
                : '<tr><td colspan="5"><div class="empty-state">暂无检查</div></td></tr>';
            box.innerHTML = Dom.scrollTable('<tr><th>单号</th><th>项目</th><th>执行科室</th><th>状态</th><th>操作</th></tr>', examRows, { wrap: false });
        }).catch(() => { box.innerHTML = '<div class="empty-state">加载失败</div>'; });
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

    /* 检验条码批量打印: 弹窗选择检验项目, 已打印灰显, 一键打印组合预览 */
    batchLabBarcodePrint() {
        const pid = this.patient.id;
        const p = this.patient;
        const self = this;
        AjaxLoader.api('lab/list?patient_id=' + pid, { silent: true }).then(r => {
            const labs = r.data || [];
            if (!labs.length) { Dom.toast('暂无检验申请可打印条码', 'info'); return; }
            const rows = labs.map(x => {
                const printed = LabBarcode.printCount(x) > 0;
                const checked = printed ? '' : 'checked';
                const status = printed
                    ? `<span class="fs-12 text-muted" title="${Format.escape([x.barcode_printed_name, x.barcode_printed_at].filter(Boolean).join(' · '))}">已打印</span>`
                    : '<span class="badge badge-warning">未打印</span>';
                return `<tr class="${printed ? 'nbp-exec-printed' : ''}">
                    <td><input type="checkbox" class="nbp-lab-check" value="${x.id}" ${checked}></td>
                    <td>${Format.escape(x.report_no || '')}</td>
                    <td>${Format.escape(x.exam_name || '')}</td>
                    <td>${Format.escape(x.specimen_type || '')}</td>
                    <td>${status}</td>
                </tr>`;
            }).join('');
            const content = `<div class="nbp-exec-sheet">
                ${Dom.scrollTable('<tr><th></th><th>单号</th><th>项目</th><th>标本</th><th>打印</th></tr>', rows)}
                <p class="fs-12 text-muted" style="margin-top:8px">默认勾选未打印的检验单；已打印的可手动勾选补打。打印后自动记录留痕。</p>
            </div>`;
            const overlay = Dom.modal('检验条码打印 - ' + Format.escape(p.patient_name || ''), content, [
                { text: '全部勾选', type: 'default', onclick: function () {
                    document.querySelectorAll('.nbp-lab-check').forEach(c => c.checked = true);
                } },
                { text: '一键打印条码', type: 'primary', onclick: async function () {
                    const ids = [...document.querySelectorAll('.nbp-lab-check:checked')].map(c => Number(c.value));
                    if (!ids.length) { Dom.toast('请勾选要打印的检验单', 'error'); return; }
                    try {
                        await AjaxLoader.api('lab/barcode-print-batch', { method: 'POST', data: { ids } });
                        self.batchLabBarcodeLabels(labs.filter(x => ids.indexOf(Number(x.id)) >= 0));
                        overlay.remove();
                        self.refresh();
                    } catch (e) { /* 已提示 */ }
                } },
                { text: '取消', type: 'default' }
            ], { large: true });
        }).catch(() => { Dom.toast('检验数据加载失败', 'error'); });
    },

    /* 组装检验条码标签预览: 选中的检验单逐条走模板, 页面展平合并为一份标签集 */
    batchLabBarcodeLabels(selected) {
        const pages = [], css = [];
        let hasAny = false;
        selected.forEach(function (x) {
            const template = PrintLabels.labBarcode(x);
            if (!template) return;
            hasAny = true;
            if (template.css && css.indexOf(template.css) < 0) css.push(template.css);
            (template.pages || [template.html]).forEach(function (page) {
                if (page && String(page).trim()) pages.push(page);
            });
        });
        if (!hasAny || !pages.length) { Dom.toast('暂无可打印的检验条码', 'info'); return; }
        PrintLabels.open({
            title: '检验条码打印',
            previewTitle: '检验条码 · 打印预览',
            paper: 'LAB_LABEL',
            css: css.join('\n'),
            pages: pages
        });
    },

    /* ================= 摆药 (已核对待摆药药品医嘱; 组合医嘱整组预摆) ================= */
    loadMedicine(box) {
        const pid = this.patient.id;
        box.innerHTML = '<div class="empty-state">加载中…</div>';
        this.setBottomBar(`<button class="btn btn-primary" onclick="NurseBedPanel.batchPrepareModal()">批量预摆药<span class="nbp-badge" id="nbp-prepare-badge" style="display:none">0</span></button>`);
        AjaxLoader.api('nurse/prepare?action=list', { silent: true }).then(r => {
            const rows = (r.data || []).filter(x => String(x.patient_id) === String(pid));
            /* 请求返回期间若已切换患者，丢弃旧响应，不能写入当前患者面板 */
            if (!this.patient || String(this.patient.id) !== String(pid) || !box.isConnected) return;
            if (!rows.length) {
                box.innerHTML = Dom.scrollTable('<tr><th>医嘱</th><th>频次</th><th>预摆</th><th>操作</th></tr>',
                    '<tr><td colspan="4"><div class="empty-state">该患者暂无待摆药药品医嘱</div></td></tr>', { wrap: false });
                this.updatePrepareBadge();
                return;
            }
            /* 组合医嘱按组展示: 每个药品一行, 子医嘱名称前制表符缩进;
               频次/预摆状态按行显示, 操作列整组合并为一个单元格一个按钮 (整组一起预摆/取消) */
            const subsByParent = {};
            const roots = [];
            rows.forEach(o => {
                if (o.parent_id) (subsByParent[o.parent_id] = subsByParent[o.parent_id] || []).push(o);
                else roots.push(o);
            });
            const statusBadge = s => s === 'prepared'
                ? '<span class="badge badge-warning">已预摆</span>'
                : (s === 'none' ? '<span class="badge badge-default">未预摆</span>' : '<span class="badge badge-success">已发药</span>');
            const trs = roots.map(o => {
                const subs = subsByParent[o.id] || [];
                const group = [o].concat(subs);
                const allPrepared = group.every(x => x.dispense_status !== 'none');
                const actCell = allPrepared
                    ? `<td rowspan="${group.length}"><button class="btn btn-small" onclick="NurseBedPanel.cancelPrepareGroup(${o.id})">取消预摆</button></td>`
                    : `<td rowspan="${group.length}" class="nbp-prepare-pending"><button class="btn btn-small btn-success" onclick="NurseBedPanel.prepareGroup(${o.id})">申请预摆</button></td>`;
                let html = `<tr>
                    <td>${Format.escape(o.content)}${o.dosage ? ' ' + Format.escape(o.dosage) : ''}</td>
                    <td>${Format.escape(o.freq_code || '')}</td>
                    <td>${statusBadge(o.dispense_status)}</td>
                    ${actCell}</tr>`;
                subs.forEach(s => {
                    html += `<tr>
                        <td class="nbp-sub-name">└ ${Format.escape(s.content)}${s.dosage ? ' ' + Format.escape(s.dosage) : ''}</td>
                        <td>${Format.escape(s.freq_code || '')}</td>
                        <td>${statusBadge(s.dispense_status)}</td></tr>`;
                });
                return html;
            }).join('');
            box.innerHTML = Dom.scrollTable('<tr><th>医嘱</th><th>频次</th><th>预摆</th><th>操作</th></tr>', trs, { wrap: false });
            this.updatePrepareBadge();
        }).catch(() => { box.innerHTML = '<div class="empty-state">加载失败</div>'; });
    },

    /* 底部按钮角标: 待预摆药组数 (未预摆的组) */
    updatePrepareBadge() {
        const badge = document.getElementById('nbp-prepare-badge');
        if (!badge) return;
        const n = document.querySelectorAll('#nbp-body .nbp-prepare-pending').length;
        badge.textContent = n;
        badge.style.display = n > 0 ? '' : 'none';
    },

    /* 单组申请预摆 (组合医嘱整组): 后端做欠费/库存前置校验, 失败逐条给出明确原因 */
    async prepareGroup(rootId) {
        try {
            const r = await AjaxLoader.api('nurse/prepare', { method: 'POST', data: { action: 'apply', ids: [rootId], plan_time: '' } });
            this.showPrepareResult(r);
            this.refresh();
            if (typeof NurseStation.refresh === 'function') NurseStation.refresh();
        } catch (e) { /* 已提示 */ }
    },

    /* 单组取消预摆 (组合医嘱整组撤回) */
    cancelPrepareGroup(rootId) {
        Dom.confirm('取消预摆药', '确认取消该组预摆药申请？组合医嘱将整组撤回。', async function () {
            await AjaxLoader.api('nurse/prepare', { method: 'POST', data: { action: 'cancel', id: rootId } });
            Dom.toast('已取消预摆', 'success');
            NurseBedPanel.refresh();
        });
    },

    /* 批量预摆药: 弹窗列出该患者尚未摆药的药品医嘱 (组合医嘱整组一行), 复选框 + 表头全选;
       确认后后端做欠费/库存前置校验, 失败逐组给出明确原因 (欠费金额 / 具体哪个药没货) */
    batchPrepareModal() {
        const pid = this.patient.id;
        const p = this.patient;
        const self = this;
        AjaxLoader.api('nurse/prepare?action=list', { silent: true }).then(r => {
            const rows = (r.data || []).filter(x => String(x.patient_id) === String(pid) && x.dispense_status === 'none');
            if (!rows.length) { Dom.toast('该患者暂无待预摆药的药品医嘱', 'info'); return; }
            const subsByParent = {};
            const roots = [];
            rows.forEach(o => {
                if (o.parent_id) (subsByParent[o.parent_id] = subsByParent[o.parent_id] || []).push(o);
                else roots.push(o);
            });
            const trs = roots.map(o => {
                const subs = (subsByParent[o.id] || []).map(s => `<div class="nbp-sub-order">${Format.escape(s.content)}${s.dosage ? ' ' + Format.escape(s.dosage) : ''}</div>`).join('');
                const content = `${Format.escape(o.content)}${o.dosage ? ' ' + Format.escape(o.dosage) : ''}${subs ? `<div class="nbp-sub-wrap">${subs}</div>` : ''}`;
                return `<tr>
                    <td><input type="checkbox" class="nbp-med-check" value="${o.id}" checked></td>
                    <td>${content}</td>
                    <td>${Format.escape(o.freq_code || '')}</td>
                    <td>${o.order_period === 'temp' ? '临时' : '长期'}</td>
                </tr>`;
            }).join('');
            const content = `<div class="nbp-exec-sheet">
                ${Dom.scrollTable('<tr><th class="nbp-check-all"><input type="checkbox" id="nbp-med-all" checked title="全选/全不选"></th><th>医嘱</th><th>频次</th><th>类型</th></tr>', trs)}
                <div class="flex-between mt-10">
                    <span class="text-muted">计划时间：<input type="datetime-local" id="nbp-plan-time" class="form-control" style="width:200px;padding:3px 8px"></span>
                    <span class="fs-12 text-muted">共 ${roots.length} 组；组合医嘱整组预摆，预摆时校验欠费与药房库存</span>
                </div>
            </div>`;
            const overlay = Dom.modal('批量预摆药 - ' + Format.escape(p.patient_name || ''), content, [
                { text: '批量预摆药', type: 'primary', onclick: async function () {
                    const ids = [...document.querySelectorAll('.nbp-med-check:checked')].map(c => Number(c.value));
                    if (!ids.length) { Dom.toast('请先勾选要预摆药的医嘱', 'error'); return; }
                    const plan = document.getElementById('nbp-plan-time');
                    try {
                        const res = await AjaxLoader.api('nurse/prepare', { method: 'POST', data: { action: 'apply', ids, plan_time: plan ? plan.value : '' } });
                        self.showPrepareResult(res);
                        overlay.remove();
                        self.refresh();
                        if (typeof NurseStation.refresh === 'function') NurseStation.refresh();
                    } catch (e) { /* 已提示 */ }
                } },
                { text: '取消', type: 'default' }
            ], { large: true });
            /* 表头复选框: 全选/全不选 */
            const all = overlay.querySelector('#nbp-med-all');
            if (all) all.addEventListener('change', function () {
                overlay.querySelectorAll('.nbp-med-check').forEach(c => { c.checked = all.checked; });
            });
        }).catch(() => { Dom.toast('待摆药数据加载失败', 'error'); });
    },

    /* 预摆药结果: 全部成功轻提示; 有失败时弹窗逐条展示明确原因 (欠费金额 / 具体药品库存不足) */
    showPrepareResult(r) {
        const fails = (r.data && r.data.fail) || [];
        if (!fails.length) { Dom.toast(r.message || '预摆药申请完成', 'success'); return; }
        Dom.modal('预摆药结果', `
            <p>${Format.escape(r.message || '部分医嘱预摆失败')}</p>
            <div class="nbp-fail-list">${fails.map(f => `<div class="nbp-fail-item">${Format.escape(f)}</div>`).join('')}</div>`,
            [{ text: '知道了', type: 'default' }]);
    },

    /* ================= 体温单 (从护理记录单派生, 只读) ================= */
    /* ================= 体温单 (三测单: 标准网格曲线, 数据源护理记录单; demo=1 全场景演示) ================= */
    loadTemperature(box) {
        const pid = this.patient.id;
        this.setBottomBar(`<button class="btn" onclick="NurseBedPanel.tempPreview()">打印预览</button>`);
        box.innerHTML = '<div class="empty-state">加载中…</div>';
        const url = this._tempDemo
            ? 'nurse/temperature-chart?demo=1'
            : 'nurse/temperature-chart?patient_id=' + pid + (this._tempStart ? '&start=' + this._tempStart : '');
        AjaxLoader.api(url, { silent: true }).then(r => {
            /* 请求返回期间若已切换患者，丢弃旧响应 */
            if (!this.patient || String(this.patient.id) !== String(pid) || !box.isConnected) return;
            const d = r.data || {};
            this._tempData = d;
            const hasVitals = (d.days || []).some(day => day.slots.some(s => s.temp || s.pulse != null || s.breath != null));
            const range = (d.start && d.end) ? Format.escape(d.start.slice(5) + ' ~ ' + d.end.slice(5)) : '';
            box.innerHTML = `<div class="temp-date-nav">
                <button class="btn btn-small" onclick="NurseBedPanel.tempPage(-1)" ${d.mode === 'demo' || !d.has_older ? 'disabled' : ''}>‹ 上一周</button>
                <span class="td-date">体温单 ${range}</span>
                <button class="btn btn-small" onclick="NurseBedPanel.tempPage(1)" ${d.mode === 'demo' || !d.has_newer ? 'disabled' : ''}>下一周 ›</button>
                ${d.mode === 'demo'
                    ? '<span class="badge badge-info">演示数据</span><button class="btn btn-small" onclick="NurseBedPanel.tempExitDemo()">返回患者数据</button>'
                    : '<button class="btn btn-small" onclick="NurseBedPanel.tempDemo()">查看演示数据</button>'}
            </div><div class="temp-chart-host"></div>`;
            if (!hasVitals && d.mode !== 'demo') {
                box.querySelector('.temp-chart-host').innerHTML = '<div class="empty-state">该患者此周无体征记录（护理记录单填写生命体征后自动生成）</div>';
                return;
            }
            TempChart.render(box.querySelector('.temp-chart-host'), d);
        }).catch(() => { box.innerHTML = '<div class="empty-state">加载失败</div>'; });
    },

    /* 体温单按周翻页 */
    tempPage(delta) {
        const d = this._tempData;
        if (!d || d.mode === 'demo' || !d.start) return;
        const dt = new Date(d.start + 'T00:00:00');
        dt.setDate(dt.getDate() + delta * 7);
        const y = dt.getFullYear(), m = ('0' + (dt.getMonth() + 1)).slice(-2), dd = ('0' + dt.getDate()).slice(-2);
        this._tempStart = y + '-' + m + '-' + dd;
        this.loadTab('temperature');
    },

    tempDemo() { this._tempDemo = true; this.loadTab('temperature'); },
    tempExitDemo() { this._tempDemo = false; this._tempStart = ''; this.loadTab('temperature'); },

    /* 打印预览: A4 纵向每页 7 天, 患者抬头占位 + HTML 表格框架 + SVG 曲线 (打印色彩保真) */
    tempPreview() {
        const d = this._tempData;
        if (!d || !(d.days || []).length) { Dom.toast('暂无体温数据可预览', 'info'); return; }
        const patient = d.mode === 'demo' ? d.patient : (this.patient || d.patient);
        const pages = TempChart.buildPrintPages(d, patient);
        if (!pages.length) { Dom.toast('暂无体温数据可预览', 'info'); return; }
        PrintPreview.combine('体温单 · 打印预览', pages, { canPrint: true });
    },

    /* ================= 护理记录单 (扩展字段: 生命体征/管路/引流/意识/血糖/瞳孔/GCS) ================= */
    loadNotes(box) {
        const pid = this.patient.id;
        this.setBottomBar(`
            <button class="btn btn-primary" onclick="NurseBedPanel.noteAdd()">新增记录</button>
            <button class="btn" onclick="NurseBedPanel.notePreview()">打印预览</button>`);
        box.innerHTML = '<div class="empty-state">加载中…</div>';
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
        if (x.heart_rate != null) m.push('心率 ' + Format.escape(String(x.heart_rate)));
        if (x.respiratory_rate != null) m.push('呼吸 ' + Format.escape(String(x.respiratory_rate)));
        if (x.pain_score != null) m.push('疼痛 ' + Format.escape(String(x.pain_score)) + '分');
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
            const pages = NursePrint.notesLandscapePages(rows, p);
            if (!pages.length) { Dom.toast('暂无护理记录可预览', 'info'); return; }
            PrintPreview.combine('护理记录单 · 打印预览', pages, { canPrint: true });
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
            <div class="form-group"><label class="form-label">体温途径</label>
                <select name="temp_type" class="form-control">
                    <option value="axillary">腋表</option><option value="oral">口表</option>
                    <option value="rectal">肛表</option><option value="ear">耳表</option>
                </select></div>
            <div class="form-group"><label class="form-label">降温后复测(℃)</label><input name="cooling_temp" class="form-control" placeholder="降温半小时后复测"></div>
            <div class="form-group"><label class="form-label">脉搏(次/分)</label><input name="pulse" class="form-control"></div>
            <div class="form-group"><label class="form-label">心率(次/分)</label><input name="heart_rate" class="form-control" placeholder="并存且更快时绘短绌"></div>
            <div class="form-group"><label class="form-label">呼吸(次/分)</label><input name="respiratory_rate" class="form-control"></div>
            <div class="form-group"><label class="form-label">疼痛评分(0-10)</label><input name="pain_score" type="number" min="0" max="10" class="form-control"></div>
            <div class="form-group"><label class="form-label">呼吸机</label>
                <select name="ventilator" class="form-control"><option value="">未使用</option><option value="1">使用中</option></select></div>
            <div class="form-group"><label class="form-label">起搏器</label>
                <select name="pacemaker" class="form-control"><option value="">无</option><option value="1">有</option></select></div>
            <div class="form-group"><label class="form-label">血压</label><span class="bp-pair"><input name="bp_systolic" class="form-control" placeholder="收缩压" style="width:60%"><span class="fs-12 text-muted">/</span><input name="bp_diastolic" class="form-control" placeholder="舒张压" style="width:35%"></span></div>
            <div class="form-group"><label class="form-label">血氧(%)</label><input name="oxygen_saturation" class="form-control"></div>
            <div class="form-group"><label class="form-label">入量(ml)</label><input name="intake_ml" class="form-control"></div>
            <div class="form-group"><label class="form-label">出量(ml)</label><input name="output_ml" class="form-control"></div>
            <div class="form-group"><label class="form-label">小便量(ml)</label><input name="urine_ml" class="form-control"></div>
            <div class="form-group"><label class="form-label">大便次数</label><input name="stool" class="form-control" placeholder="如 0/E·3/2E·※·☆"></div>
            <div class="form-group"><label class="form-label">意识情况</label>
                <select name="consciousness" class="form-control"><option value="">选填</option><option>清醒</option><option>嗜睡</option><option>昏睡</option><option>昏迷</option><option>模糊</option></select></div>
            <div class="form-group"><label class="form-label">管路</label>
                <select name="has_pipeline" class="form-control"><option value="">无</option><option>胃管</option><option>尿管</option><option>深静脉</option><option>引流管</option><option>其他</option></select></div>
            <div class="form-group"><label class="form-label">管路描述</label><input name="pipeline_desc" class="form-control" placeholder="如: 胃管留置中"></div>
            <div class="form-group"><label class="form-label">引流量(ml)</label><input name="drainage_ml" class="form-control"></div>
            <div class="form-group"><label class="form-label">血糖(mmol/L)</label><input name="blood_glucose" class="form-control"></div>
            <div class="form-group"><label class="form-label">瞳孔情况</label><input name="pupil" class="form-control" placeholder="如: 等大等圆 对光反射灵敏"></div>
            <div class="form-group"><label class="form-label">皮试结果</label>
                <select name="skin_test" class="form-control"><option value="">未做</option><option>阴性</option><option>阳性</option></select></div>
            <div class="form-group"><label class="form-label">过敏药物</label><input name="allergy_drugs" class="form-control" placeholder="如: 青霉素"></div>
            <div class="form-group"><label class="form-label">特殊药物</label><input name="special_drugs" class="form-control" placeholder="如: 12/30接种100"></div>
            <div class="form-group"><label class="form-label">GCS 睁眼</label><select name="gcs_eye" class="form-control"><option value="">-</option><option value="4">4 自发</option><option value="3">3 呼唤</option><option value="2">2 疼痛</option><option value="1">1 无</option></select></div>
            <div class="form-group"><label class="form-label">GCS 语言</label><select name="gcs_verbal" class="form-control"><option value="">-</option><option value="5">5 正常</option><option value="4">4 混乱</option><option value="3">3 词语</option><option value="2">2 声音</option><option value="1">1 无</option></select></div>
            <div class="form-group"><label class="form-label">GCS 运动</label><select name="gcs_motor" class="form-control"><option value="">-</option><option value="6">6 遵嘱</option><option value="5">5 定位</option><option value="4">4 回缩</option><option value="3">3 屈曲</option><option value="2">2 过伸</option><option value="1">1 无</option></select></div>
            <div class="form-group colspan-2"><label class="form-label">其他备注</label><input name="other_remark" class="form-control" placeholder="选填"></div>
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
        this.setBottomBar(`<button class="btn btn-primary" onclick="NurseBedPanel.docAdd()">新建文书</button>`);
        box.innerHTML = '<div class="empty-state">加载中…</div>';
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
        this.setBottomBar(`
            <button class="btn btn-primary" onclick="NurseBedPanel.transferWard()">转科</button>
            <button class="btn btn-danger" onclick="NurseBedPanel.doDischarge()">办理出院</button>
            <button class="btn" onclick="NurseBedPanel.changeBed()">迁床</button>
            <button class="btn" onclick="NurseBedPanel.chargeCheck()">费用核对</button>
            <button class="btn" onclick="NurseBedPanel.chargeAdd()">费用补录</button>`);
        box.innerHTML = `<div class="kv-grid">
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
                    ${Dom.scrollTable('<tr><th>项目</th><th>数量</th><th>单价</th><th>金额</th><th>来源</th><th>操作</th></tr>', rows || '<tr><td colspan="6"><div class="empty-state">暂无费用</div></td></tr>')}
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
