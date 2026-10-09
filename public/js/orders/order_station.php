<?php header("Content-Type: text/html; charset=utf-8"); ?>
<div class="order-station">
    <div class="os-toolbar">
        <button type="button" class="os-tool" onclick="OrderStation.openMedication()">＋ 药品<span class="key">F9</span></button>
        <button type="button" class="os-tool" onclick="OrderStation.openNonmedication()">＋ 非药品<span class="key">F8</span></button>
        <span class="os-sep"></span>
        <button type="button" class="os-tool os-tool-success" onclick="OrderStation.submitSelected()">提交<span class="key">F2</span></button>
        <button type="button" class="os-tool os-tool-danger" onclick="OrderStation.stopAllLong()">长期全停<span class="key">F4</span></button>
        <button type="button" class="os-tool" onclick="OrderStation.refresh()">刷新<span class="key">F5</span></button>
        <span class="os-sep"></span>
        <button type="button" class="os-tool" onclick="OrderStation.openSetPicker()">路径/组套</button>
        <button type="button" class="os-tool" onclick="OrderStation.batchDelete()">批量删除</button>
        <span id="os-selected-count" class="os-selected-count"></span>
    </div>

    <div class="os-filter">
        <span class="os-filter-group">
            <label class="os-filter-radio"><input type="radio" name="os-period-filter" value="" checked onchange="OrderStation.setFilter('period', '')">全部</label>
            <label class="os-filter-radio"><input type="radio" name="os-period-filter" value="long" onchange="OrderStation.setFilter('period', 'long')">长期</label>
            <label class="os-filter-radio"><input type="radio" name="os-period-filter" value="temp" onchange="OrderStation.setFilter('period', 'temp')">临时</label>
        </span>
        <span class="os-sep"></span>
        <span class="os-filter-group">
            按开始日期
            <input type="date" id="os-date-from" onchange="OrderStation.setFilter('date_from', this.value)">
            至
            <input type="date" id="os-date-to" onchange="OrderStation.setFilter('date_to', this.value)">
        </span>
        <span class="os-sep"></span>
        <span class="os-filter-group">
            状态
            <label class="os-filter-radio"><input type="radio" name="os-status-filter" value="" checked onchange="OrderStation.setFilter('status', '')">全部</label>
            <label class="os-filter-radio"><input type="radio" name="os-status-filter" value="draft,pending" onchange="OrderStation.setFilter('status', 'draft,pending')">医嘱本</label>
            <label class="os-filter-radio"><input type="radio" name="os-status-filter" value="invalid" onchange="OrderStation.setFilter('status', 'invalid')">作废</label>
            <label class="os-filter-radio"><input type="radio" name="os-status-filter" value="stopped" onchange="OrderStation.setFilter('status', 'stopped')">停止</label>
            <label class="os-filter-radio"><input type="radio" name="os-status-filter" value="pending,verified,executing" onchange="OrderStation.setFilter('status', 'pending,verified,executing')">待完成</label>
        </span>
    </div>

    <div class="os-table-wrap">
        <table class="os-table">
            <colgroup>
                <col class="os-col-check"><col class="os-col-period"><col class="os-col-time">
                <col><col class="os-col-dosage"><col class="os-col-usage">
                <col class="os-col-doctor"><col class="os-col-op">
            </colgroup>
            <thead>
                <tr>
                    <th class="os-col-check"></th>
                    <th class="os-col-period">长期<br>临时</th>
                    <th>开始时间</th>
                    <th>医嘱内容</th>
                    <th>剂量</th>
                    <th></th>
                    <th>医生</th>
                    <th></th>
                </tr>
            </thead>
            <tbody id="os-tbody"></tbody>
        </table>
        <div id="os-empty" class="os-empty" style="display:none">暂无医嘱</div>
    </div>
</div>

<script>
var OrderStation = {
    patientId: null,
    filters: { period: '', status: '', date_from: '', date_to: '' },
    groups: [],
    selected: new Set(),
    keyHandler: null,

    init() {
        const ctx = PatientContext.current();
        if (!ctx) {
            Dom.toast('请先选择患者', 'error');
            return;
        }
        this.patientId = ctx.id;
        this.selected.clear();
        this.bindKeys();
        this.refresh();
    },

    bindKeys() {
        if (this.keyHandler) document.removeEventListener('keydown', this.keyHandler);
        this.keyHandler = (e) => {
            const appContent = document.getElementById('app-content');
            if (!document.getElementById('os-tbody') || (appContent && appContent.style.display === 'none')) {
                document.removeEventListener('keydown', this.keyHandler);
                this.keyHandler = null;
                return;
            }
            if (e.key === 'F9') { e.preventDefault(); this.openMedication(); }
            else if (e.key === 'F8') { e.preventDefault(); this.openNonmedication(); }
            else if (e.key === 'F2') { e.preventDefault(); this.submitSelected(); }
            else if (e.key === 'F4') { e.preventDefault(); this.stopAllLong(); }
            else if (e.key === 'F5') { e.preventDefault(); this.refresh(); }
        };
        document.addEventListener('keydown', this.keyHandler);
    },

    setFilter(key, value) {
        this.filters[key] = value;
        this.refresh();
    },

    async refresh() {
        if (!this.patientId) return;
        const qs = new URLSearchParams({ patient_id: this.patientId, per_page: 500 });
        if (this.filters.period) qs.set('order_period', this.filters.period);
        if (this.filters.status) qs.set('status', this.filters.status);
        if (this.filters.date_from) qs.set('date_from', this.filters.date_from);
        if (this.filters.date_to) qs.set('date_to', this.filters.date_to);
        try {
            const r = await AjaxLoader.api('orders/list?' + qs.toString(), { silent: true });
            this.groups = r.data || [];
            this.selected.clear();
            this.render();
        } catch (e) { /* 已提示 */ }
    },

    render() {
        const tbody = document.getElementById('os-tbody');
        const empty = document.getElementById('os-empty');
        if (!tbody) return;
        empty.style.display = this.groups.length === 0 ? 'block' : 'none';
        tbody.innerHTML = this.groups.map(g => this.renderGroup(g)).join('');
        this.updateSelectedHint();
    },

    renderGroup(root) {
        const rows = [root].concat(root.sub_orders || []);
        const size = rows.length;
        const dim = ['invalid', 'stopped'].includes(root.status) ? ' os-row-dim' : '';
        let html = '';
        rows.forEach((o, i) => {
            const first = i === 0;
            const span = first ? ` rowspan="${size}"` : '';
            html += `<tr class="${first ? 'os-group-start' : ''}${dim}">`;
            if (first) {
                html += `<td class="os-col-check"${span}><input type="checkbox" ${this.selected.has(root.id) ? 'checked' : ''} onchange="OrderStation.toggleSelect(${root.id}, this.checked)"></td>`;
                html += `<td class="os-col-period"${span}>${this.periodBadge(root.order_period)}</td>`;
                html += `<td>${this.timeText(root)}</td>`;
            }
            html += `<td>${this.statusChip(o)}${Format.escape(o.content)}${this.tags(o.billing_flags)}</td>`;
            html += `<td class="os-col-dosage">${Format.escape(o.dosage || '')}${o.dosage_unit ? ' ' + Format.escape(o.dosage_unit) : ''}</td>`;
            if (first) {
                const usageCls = size > 1 ? 'os-usage-brace' : 'os-usage-single';
                html += `<td class="os-col-usage ${usageCls}"${span}>${this.usageText(root, o)}</td>`;
                html += `<td class="os-col-doctor"${span}>${Format.escape(root.creator_name || '')}</td>`;
                html += `<td class="os-col-op"${span}><button type="button" class="os-op-btn" onclick="OrderActions.show(${root.id}, event)" title="操作">⋯</button></td>`;
            }
            html += '</tr>';
        });
        return html;
    },

    periodBadge(period) {
        return period === 'temp'
            ? '<span class="os-period os-period-temp">临</span>'
            : '<span class="os-period os-period-long">长</span>';
    },

    statusChip(o) {
        if (['draft', 'pending', 'stopped', 'invalid'].includes(o.status)) {
            return `<span class="os-status-chip os-status-${o.status}">${Format.orderStatus(o.status)}</span>`;
        }
        return '';
    },

    tags(flags) {
        if (!flags) return '';
        const map = { '径外': 'os-tag-jingwai', '自费': 'os-tag-zifei', '不计价': 'os-tag-bujijia', '预防': 'os-tag-yufang' };
        return flags.split(',').filter(Boolean).map(f =>
            `<span class="os-tag ${map[f] || 'os-tag-bujijia'}">${Format.escape(f)}</span>`
        ).join('');
    },

    usageText(root, o) {
        const usage = o.usage_name || root.usage_name || '';
        const rate = o.usage_rate || root.usage_rate || '';
        const freq = o.freq_code || o.frequency || '';
        const detail = o.freq_detail || '';
        let parts = [];
        if (usage) parts.push(Format.escape(usage));
        if (rate) parts.push(Format.escape(rate));
        let text = `<span class="os-usage-text">${parts.join(' ')}</span>`;
        if (freq || detail) text += ` <span class="os-usage-rate">${Format.escape(freq)} ${Format.escape(detail)}</span>`;
        return text;
    },

    timeText(o) {
        const start = Format.shortTime(o.start_date || o.created_at);
        if (o.stop_date) {
            return `${start}<div style="font-size:11px;color:#909399">停 ${Format.shortTime(o.stop_date)}</div>`;
        }
        return start;
    },

    toggleSelect(id, checked) {
        if (checked) this.selected.add(id); else this.selected.delete(id);
        this.updateSelectedHint();
    },

    updateSelectedHint() {
        const el = document.getElementById('os-selected-count');
        if (el) el.textContent = this.selected.size ? `已选 ${this.selected.size} 组` : '';
    },

    selectedRoots() {
        return this.groups.filter(g => this.selected.has(g.id));
    },

    openMedication() { if (this.patientId) OrderEditor.open('medication', this.patientId); },
    openNonmedication() { if (this.patientId) OrderEditor.open('nonmedication', this.patientId); },
    submitSelected() { OrderActions.submitSelected(); },
    batchDelete() { OrderActions.batchDelete(); },
    stopAllLong() { OrderActions.stopAllLong(); },
    openSetPicker() { OrderActions.openSetPicker(); }
};

async function orders_order_station_init() {
    document.getElementById('main-app').style.display = 'flex';
    document.getElementById('login-container').style.display = 'none';
    const user = Auth.user;
    const sidebar = await fetch('/js/shared/sidebar_' + user.role + '.php').then(r => r.text());
    document.getElementById('sidebar').innerHTML = sidebar;
    OrderStation.init();
}
</script>
