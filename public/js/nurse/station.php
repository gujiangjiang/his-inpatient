<?php header("Content-Type: text/html; charset=utf-8"); require_once __DIR__ . '/../../icons/icon.php'; ?>
<?php include __DIR__ . '/nurse_bed_panel.php'; ?>
<div class="nurse-board">
    <div class="nurse-board-head">
        <span class="nb-title">床位一览表</span>
        <span class="fs-12 text-muted" id="nb-board-hint"></span>
        <div class="nb-tools">
            <button class="btn btn-small" onclick="NurseStation.batchPrepare()"><?= icon('common/nav', 'dispensing', 13) ?> 批量预摆药</button>
            <button class="btn btn-small" onclick="NurseStation.preparePage()">预摆药清单</button>
            <button class="btn btn-small" onclick="NurseStation.checklistPage()">核查清单</button>
            <button class="btn btn-small btn-primary" onclick="NurseStation.refresh()">刷新</button>
        </div>
    </div>
    <div class="nurse-ward-stat" id="nb-ward-stat">
        <span class="ns-item">病区：<b id="nb-ward-name">-</b></span>
        <span class="ns-item">床位：<b id="nb-bed-occ">-</b>/<b id="nb-bed-total">-</b></span>
        <span class="ns-item">在院患者：<b id="nb-patient-cnt">0</b></span>
        <span class="ns-item">待核查医嘱：<b id="nb-pending-cnt">0</b></span>
        <span class="ns-item">未发药：<b id="nb-undisp-cnt">0</b></span>
    </div>
    <div class="nurse-ward-tabs" id="nb-ward-tabs"></div>
    <div class="nurse-bed-scroll" id="nb-bed-scroll">
        <div class="nurse-bed-grid" id="nb-bed-grid"></div>
    </div>
    <div class="nurse-batchbar" id="nb-batchbar" style="display:none">
        <span class="nbb-hint">批量预摆药：</span>
        <span class="nbb-count" id="nb-batch-count">已选 0 条</span>
        <span class="text-muted">计划时间：<input type="datetime-local" id="nb-plan-time" style="padding:3px 8px;border:1px solid var(--border);border-radius:6px"></span>
        <button class="btn btn-small btn-primary" onclick="NurseStation.batchPrepareApply()">申请预摆药（勾选）</button>
        <span class="text-muted" id="nb-batch-hint"></span>
    </div>
</div>

<script>
var NurseStation = {
    data: [],           // bedboard 数据 [{ward, beds}]
    activeWardId: null,
    batchSel: new Set(),
    batchRows: [],

    async init() {
        await App.mountShell('nurse');
        this.refresh();
    },

    async refresh() {
        try {
            const r = await AjaxLoader.api('nurse/bedboard', { silent: true });
            this.data = r.data || [];
            this.render();
        } catch (e) {
            Dom.toast('床位一览表加载失败', 'error');
        }
    },

    /* 病区选择 */
    selectWard(wardId) {
        this.activeWardId = wardId;
        this.batchSel.clear();
        this.batchRows = [];
        this.render();
    },

    render() {
        const wards = this.data.map(x => x.ward);
        // 病区 Tabs
        const tabs = document.getElementById('nb-ward-tabs');
        if (!this.activeWardId && wards.length) this.activeWardId = wards[0].id;
        tabs.innerHTML = wards.map(w => `
            <div class="nurse-ward-tab${String(w.id) === String(this.activeWardId) ? ' active' : ''}"
                 onclick="NurseStation.selectWard(${w.id})">
                ${Format.escape(w.name)}<span class="tab-count">${w.bed_occupied}/${w.bed_total}</span>
            </div>`).join('');
        const ward = this.data.find(x => String(x.ward.id) === String(this.activeWardId));
        if (!ward) { document.getElementById('nb-bed-grid').innerHTML = '<div class="empty-state">暂无病区</div>'; return; }
        // 统计
        document.getElementById('nb-ward-name').textContent = ward.ward.name || '';
        document.getElementById('nb-bed-occ').textContent = ward.ward.bed_occupied || 0;
        document.getElementById('nb-bed-total').textContent = ward.ward.bed_total || 0;
        const patients = ward.beds.filter(b => b.patient_id);
        document.getElementById('nb-patient-cnt').textContent = patients.length;
        document.getElementById('nb-pending-cnt').textContent = patients.reduce((s, b) => s + (b.pending_cnt || 0), 0);
        document.getElementById('nb-undisp-cnt').textContent = patients.reduce((s, b) => s + (b.undispensed_cnt || 0), 0);
        // 床位卡片
        const grid = document.getElementById('nb-bed-grid');
        const freeCnt = ward.beds.filter(b => !b.patient_id).length;
        document.getElementById('nb-board-hint').textContent = '空闲 ' + freeCnt + ' 床 · 点击床位卡片进行操作';
        if (!ward.beds.length) { grid.innerHTML = '<div class="empty-state">该病区暂无床位</div>'; return; }
        grid.innerHTML = ward.beds.map(b => this.bedCard(b)).join('');
        // 批量摆药条: 聚合该病区待摆药医嘱
        this.renderBatchBar(ward);
    },

    bedCard(b) {
        if (!b.patient_id) {
            return `<div class="nurse-bed-card free" onclick="NurseStation.admitForm(${b.id}, '${Format.escape(b.bed_no)}')" title="点击收住新患者">
                <div class="nbc-head">
                    <span class="nbc-bed level-3">${Format.escape(b.bed_no)}</span>
                    <span class="nbc-sub nbc-label">空闲 · 点击收住</span>
                </div>
            </div>`;
        }
        const level = String(b.nursing_level || '3');
        const roman = { special: '特', '1': 'Ⅰ', '2': 'Ⅱ', '3': 'Ⅲ' }[level] || 'Ⅲ';
        const days = b.admission_date ? Math.max(1, Math.ceil((Date.now() - new Date(String(b.admission_date).replace(/-/g, '/')).getTime()) / 86400000)) : '';
        const badges = [];
        if (b.pending_cnt > 0) badges.push(`<span class="nbc-badge warn">医嘱 ${b.pending_cnt}</span>`);
        if (b.undispensed_cnt > 0) badges.push(`<span class="nbc-badge danger">待发药 ${b.undispensed_cnt}</span>`);
        if (b.lab_collect_cnt > 0) badges.push(`<span class="nbc-badge info">检验 ${b.lab_collect_cnt}</span>`);
        if (b.exam_pending_cnt > 0) badges.push(`<span class="nbc-badge info">检查 ${b.exam_pending_cnt}</span>`);
        if (b.nursing_doc_cnt === 0) badges.push(`<span class="nbc-badge">无文书</span>`);
        const cls = 'nurse-bed-card' + (b.is_critical == 1 ? ' critical' : '') + (b.allergy_history ? ' allergy' : '');
        return `<div class="${cls}" onclick="NurseBedPanel.openFromCard(this)" data-patient="${Format.escape(JSON.stringify(b))}">
            <div class="nbc-head">
                <span class="nbc-bed level-${level}">${roman}|${Format.escape(b.bed_no || '-')}</span>
                <span class="nbc-name">${Format.escape(b.patient_name || '')}</span>
                ${b.is_critical == 1 ? '<span class="badge badge-critical">危重</span>' : ''}
            </div>
            <div class="nbc-sub">${Format.gender(b.gender)} / ${b.age ? b.age + '岁' : '-'} / ${Format.escape(b.admission_no || '')}</div>
            <div class="nbc-sub nbc-diag"><span class="nbc-label">诊断:</span> ${Format.escape(b.admission_diagnosis || '-')}</div>
            <div class="nbc-badges">${badges.join('')}</div>
            <div class="nbc-foot">
                <span>${Format.escape(b.fee_type || '-')}</span>
                <span class="nbc-days">${days ? '<b>' + days + '天</b>' : ''}</span>
            </div>
        </div>`;
    },

    /* 批量摆药: 聚合当前病区所有待摆药医嘱 (patient_id 精确) */
    renderBatchBar(ward) {
        const bar = document.getElementById('nb-batchbar');
        const hint = document.getElementById('nb-batch-hint');
        const patients = ward.beds.filter(b => b.patient_id).map(b => Number(b.patient_id));
        if (!patients.length) { bar.style.display = 'none'; return; }
        AjaxLoader.api('nurse/prepare?action=list', { silent: true }).then(r => {
            const rows = (r.data || []).filter(x => patients.indexOf(Number(x.patient_id)) >= 0);
            this.batchRows = rows;
            bar.style.display = 'flex';
            hint.textContent = '当前病区待摆药 ' + rows.length + ' 条（未预摆 ' + rows.filter(x => x.dispense_status !== 'prepared').length + '）';
            this.updateBatchCount();
        }).catch(() => { bar.style.display = 'none'; });
    },

    batchPrepare() {
        const bar = document.getElementById('nb-batchbar');
        if (bar.style.display === 'none') { Dom.toast('当前病区暂无待摆药药品', 'info'); return; }
        const sel = document.getElementById('nb-batch-sel');
        if (!sel) {
            const body = document.createElement('div');
            body.id = 'nb-batch-sel';
            document.getElementById('nb-batchbar').appendChild(body);
        }
        // 打开批量选择弹窗
        const rows = this.batchRows.filter(x => x.dispense_status !== 'prepared');
        if (!rows.length) { Dom.toast('当前病区待摆药医嘱均已预摆', 'info'); return; }
        const content = `<div class="nnote-list" style="max-height:40vh">
            <table class="table">
                <thead><tr><th></th><th>患者</th><th>床号</th><th>医嘱</th><th>频次</th></tr></thead>
                <tbody>${rows.map(o => `<tr>
                    <td><input type="checkbox" class="nbb-check" value="${o.id}" checked></td>
                    <td>${Format.escape(o.patient_name || '')}</td>
                    <td>${Format.escape(o.bed_no || '')}</td>
                    <td>${Format.escape(o.content)}${o.dosage ? ' ' + Format.escape(o.dosage) : ''}</td>
                    <td>${Format.escape(o.freq_code || '')}</td>
                </tr>`).join('')}</tbody>
            </table></div>
            <div class="flex-between mt-10">
                <span class="text-muted">计划时间：<input type="datetime-local" id="nb-batch-plan" style="padding:3px 8px;border:1px solid var(--border);border-radius:6px"></span>
            </div>`;
        const overlay = Dom.modal('批量预摆药 - ' + (ward.ward.name || ''), content, [
            { text: '全部勾选', type: 'default', onclick: function () {
                document.querySelectorAll('.nbb-check').forEach(c => c.checked = true);
            } },
            { text: '申请预摆药', type: 'primary', onclick: async function () {
                const ids = [...document.querySelectorAll('.nbb-check:checked')].map(c => Number(c.value));
                if (!ids.length) { Dom.toast('请勾选要预摆药的医嘱', 'error'); return; }
                const plan = document.getElementById('nb-batch-plan');
                try {
                    await AjaxLoader.api('nurse/prepare', { method: 'POST', data: { action: 'apply', ids, plan_time: plan ? plan.value : '' } });
                    Dom.toast('已申请预摆药 ' + ids.length + ' 条', 'success');
                    overlay.remove();
                    NurseStation.refresh();
                } catch (e) { /* 已提示 */ }
            } },
            { text: '取消', type: 'default' }
        ]);
    },

    updateBatchCount() {
        const el = document.getElementById('nb-batch-count');
        if (el) el.textContent = '待摆药 ' + this.batchRows.length + ' 条';
    },

    batchPrepareApply() {
        const ids = this.batchRows.filter(x => x.dispense_status !== 'prepared').map(x => x.id);
        if (!ids.length) { Dom.toast('当前病区待摆药医嘱均已预摆', 'info'); return; }
        const plan = document.getElementById('nb-plan-time');
        AjaxLoader.api('nurse/prepare', { method: 'POST', data: { action: 'apply', ids, plan_time: plan ? plan.value : '' } }).then(() => {
            Dom.toast('已批量申请预摆药 ' + ids.length + ' 条', 'success');
            this.refresh();
        }).catch(() => {});
    },

    preparePage() { window.navigateTo('nurse', 'prepare'); },
    checklistPage() { window.navigateTo('nurse', 'checklist'); },

    /* 入科登记: 空闲床位收住新患者 (护士) */
    admitForm(bedId, bedNo) {
        const content = `<form id="admit-form" class="form-grid">
            ${Dom.formField({ name: 'name', label: '姓名', type: 'text' }, '')}
            ${Dom.formField({ name: 'gender', label: '性别', type: 'select', options: [{ label: '男', value: 'male' }, { label: '女', value: 'female' }] }, '')}
            ${Dom.formField({ name: 'birth_date', label: '出生日期', type: 'date' }, '')}
            ${Dom.formField({ name: 'id_card', label: '身份证号', type: 'text' }, '')}
            <div class="form-group colspan-2"><label class="form-label">初步诊断 <span class="text-danger">*</span></label>
                <input type="text" name="admission_diagnosis" id="nurse-admit-diag" class="form-control" placeholder="搜索 ICD-10 诊断"></div>
            <input type="hidden" name="admission_icd_code" id="nurse-admit-icd" value="">
            <input type="hidden" name="bed_id" value="${bedId}">
        </form>`;
        const overlay = Dom.modal('入科登记 - ' + bedNo + ' 床', content, [
            { text: '确认收住', type: 'primary', onclick: async function () {
                const form = document.getElementById('admit-form');
                const data = {};
                new FormData(form).forEach((v, k) => data[k] = v);
                if (!data.name || !data.gender || !data.birth_date) { Dom.toast('请填写姓名/性别/出生日期', 'error'); return; }
                if (!data.admission_diagnosis.trim()) { Dom.toast('请填写初步诊断', 'error'); return; }
                try {
                    const r = await AjaxLoader.api('nurse/admit', { method: 'POST', data });
                    Dom.toast(r.message || '已收住', 'success');
                    overlay.remove();
                    NurseStation.refresh();
                } catch (e) { /* 已提示 */ }
            } },
            { text: '取消', type: 'default' }
        ]);
        setTimeout(function () {
            if (window.IcdPicker) IcdPicker.bind(document.getElementById('nurse-admit-diag'), { codeEl: document.getElementById('nurse-admit-icd') });
        }, 100);
    }
};

async function nurse_station_init() {
    await NurseStation.init();
}
</script>