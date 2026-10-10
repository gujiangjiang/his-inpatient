<?php header("Content-Type: text/html; charset=utf-8"); ?>
<div class="order-station">
    <div class="os-toolbar">
        <button type="button" class="os-tool" onclick="OrderStation.openMedication(event)">＋ 药品<span class="key">F9</span></button>
        <button type="button" class="os-tool" onclick="OrderStation.openNonmedication(event)">＋ 非药品<span class="key">F8</span></button>
        <span class="os-sep"></span>
        <button type="button" class="os-tool os-tool-success" onclick="OrderStation.submitSelected()">提交<span class="key">F2</span></button>
        <button type="button" class="os-tool os-tool-danger" onclick="OrderStation.stopAllLong()">长期全停<span class="key">F4</span></button>
        <button type="button" class="os-tool" onclick="OrderStation.refresh(true)">刷新<span class="key">F5</span></button>
        <span class="os-sep"></span>
        <button type="button" class="os-tool" onclick="OrderStation.openSetPicker()">路径/组套</button>
        <button type="button" class="os-tool" onclick="OrderStation.toggleAssist()">辅助决策</button>
        <button type="button" class="os-tool" onclick="OrderStation.batchDelete()">批量删除</button>
        <span id="os-selected-count" class="os-selected-count"></span>
    </div>

    <!-- 临床辅助决策悬浮卡 -->
    <div id="os-assist" class="os-assist" style="display:none">
        <div class="os-assist-head">
            <span>临床辅助决策</span>
            <span id="os-assist-name" class="os-assist-name"></span>
            <button type="button" class="os-assist-close" onclick="OrderStation.toggleAssist()">关闭</button>
        </div>
        <div id="os-assist-body" class="os-assist-body"></div>
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
    editing: null,
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
        this.fillAssist(ctx);
        this.refresh();
    },

    fillAssist(p) {
        const nameEl = document.getElementById('os-assist-name');
        const body = document.getElementById('os-assist-body');
        if (!nameEl || !body) return;
        nameEl.textContent = p.name || '';
        const age = p.birth_date ? (new Date().getFullYear() - new Date(p.birth_date).getFullYear()) : '';
        let html = '';
        if (p.allergy_history) {
            html += '<div class="os-assist-warn">' + svgIcon('common/status/warning', 14) + ' 过敏史：' + Format.escape(p.allergy_history) + '</div>';
        }
        if (p.is_critical == 1) {
            html += '<div class="os-assist-warn">' + svgIcon('common/status/warning', 14) + ' 危重患者，开嘱请谨慎</div>';
        }
        html += '<div class="os-assist-line"><b>诊断</b>' + Format.escape(p.admission_diagnosis || '-') + '</div>';
        html += '<div class="os-assist-line"><b>VTE</b>Caprini ' + Format.escape(p.vte_caprini || '未评') + ' · 出血 ' + Format.escape(p.vte_bleeding || '未评') + '</div>';
        html += '<div class="os-assist-line"><b>费用</b>¥ ' + Number(p.total_fee || 0).toLocaleString('zh-CN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' · ' + Format.escape(p.fee_type || '') + '</div>';
        html += '<div class="os-assist-line os-assist-tip">开嘱时请注意药物相互作用与医保限制</div>';
        body.innerHTML = html;
        if (sessionStorage.getItem('os_assist_hidden_' + p.id) !== '1' && (p.allergy_history || p.is_critical == 1)) {
            document.getElementById('os-assist').style.display = 'block';
        }
    },

    toggleAssist() {
        const el = document.getElementById('os-assist');
        if (!el) return;
        const show = el.style.display === 'none';
        el.style.display = show ? 'block' : 'none';
        if (!show && this.patientId) sessionStorage.setItem('os_assist_hidden_' + this.patientId, '1');
        else if (this.patientId) sessionStorage.removeItem('os_assist_hidden_' + this.patientId);
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

    async refresh(feedback) {
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
            this.editing = null;
            this.render();
            if (feedback) Dom.toast('已刷新 ' + Format.shortTime(new Date()) + ' · 共 ' + this.groups.length + ' 组医嘱', 'success');
        } catch (e) { /* 已提示 */ }
    },

    render() {
        const tbody = document.getElementById('os-tbody');
        const empty = document.getElementById('os-empty');
        if (!tbody) return;
        empty.style.display = this.groups.length === 0 ? 'block' : 'none';
        tbody.innerHTML = this.groups.map(g => this.renderGroup(g)).join('');
        const form = tbody.querySelector('.os-edit-form');
        if (form) {
            form.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' && !['BUTTON', 'SELECT'].includes(e.target.tagName)) {
                    e.preventDefault();
                    this.saveEditRow();
                } else if (e.key === 'Escape') {
                    e.preventDefault();
                    this.cancelEdit();
                }
            });
        }
        this.updateSelectedHint();
    },

    renderGroup(root) {
        const rows = [root].concat(root.sub_orders || []);
        const size = rows.length;
        const dim = ['invalid', 'stopped'].includes(root.status) ? ' os-row-dim' : '';
        const editable = root.status === 'draft';
        let html = '';
        rows.forEach((o, i) => {
            const first = i === 0;
            const span = first ? ` rowspan="${size}"` : '';
            const clickable = editable ? ` onclick="OrderStation.toggleEditRow(${o.id}, ${root.id}, event)"` : '';
            html += `<tr class="${first ? 'os-group-start' : ''}${dim}${editable ? ' os-row-editable' : ''}">`;
            if (first) {
                html += `<td class="os-col-check"${span}><input type="checkbox" ${this.selected.has(root.id) ? 'checked' : ''} onchange="OrderStation.toggleSelect(${root.id}, this.checked)"></td>`;
                html += `<td class="os-col-period"${span}>${this.periodBadge(root.order_period)}</td>`;
                html += `<td>${this.timeText(root)}</td>`;
            } else {
                // 子医嘱: 补时间列占位, 内容保持在"医嘱内容"列
                html += '<td></td>';
            }
            html += `<td${clickable} title="${editable ? '点击修改' : ''}">${this.statusChip(o)}${Format.escape(o.content)}${this.tags(o.billing_flags)}</td>`;
            html += `<td class="os-col-dosage"${clickable}>${Format.escape(o.dosage || '')}${o.dosage_unit ? ' ' + Format.escape(o.dosage_unit) : ''}</td>`;
            if (first) {
                const usageCls = size > 1 ? 'os-usage-brace' : 'os-usage-single';
                html += `<td class="os-col-usage ${usageCls}"${span}>${this.usageText(root, o)}</td>`;
                html += `<td class="os-col-doctor"${span}>${Format.escape(root.creator_name || '')}</td>`;
                html += `<td class="os-col-op"${span}><button type="button" class="os-op-btn" onclick="OrderActions.show(${root.id}, event)" title="操作">${svgIcon('common/actions/more', 16)}</button></td>`;
            }
            html += '</tr>';
            if (this.editing && this.editing.orderId === o.id) {
                html += this.editRowHtml(o);
            }
        });
        return html;
    },

    editRowHtml(o) {
        const freqOpts = (typeof OrderEditor !== 'undefined' ? OrderEditor.FREQS : [['', '无']])
            .map(f => `<option value="${f[0]}" ${f[0] === (o.freq_code || o.frequency || '') ? 'selected' : ''}>${f[1]}</option>`).join('');
        const usages = ['静脉输液', '静脉注射', '口服', '皮下注射', '肌肉注射', '外用', '雾化吸入', '护理', '测量', '饮食', '陪护'];
        const usageOpts = usages.map(u => `<option value="${u}" ${u === o.usage_name ? 'selected' : ''}>${u}</option>`).join('');
        const flags = (o.billing_flags || '').split(',');
        const flagBox = (v) => `<label><input type="checkbox" class="os-edit-flag" value="${v}" ${flags.includes(v) ? 'checked' : ''}>${v}</label>`;
        return `<tr class="os-edit-tr"><td colspan="8">
            <div class="os-edit-form" data-id="${o.id}">
                <label>内容 <input class="os-edit-content" value="${Format.escape(o.content)}"></label>
                <label>剂量 <input class="os-edit-w os-edit-dosage" value="${Format.escape(o.dosage || '')}"></label>
                <label>单位 <input class="os-edit-w os-edit-unit" value="${Format.escape(o.dosage_unit || '')}"></label>
                <label>用法 <select class="os-edit-usage"><option value="">-</option>${usageOpts}</select></label>
                <label>滴速 <input class="os-edit-w os-edit-rate" value="${Format.escape(o.usage_rate || '')}"></label>
                <label>频次 <select class="os-edit-freq">${freqOpts}</select></label>
                <label>时间 <input class="os-edit-w os-edit-detail" value="${Format.escape(o.freq_detail || '')}"></label>
                <label>期间 <select class="os-edit-period">
                    <option value="long" ${o.order_period !== 'temp' ? 'selected' : ''}>长期</option>
                    <option value="temp" ${o.order_period === 'temp' ? 'selected' : ''}>临时</option>
                </select></label>
                <span class="os-edit-flags">${flagBox('径外')}${flagBox('自费')}${flagBox('不计价')}${flagBox('预防')}</span>
                <button type="button" class="btn btn-small btn-primary" onclick="OrderStation.saveEditRow()">保存</button>
                <button type="button" class="btn btn-small" onclick="OrderStation.cancelEdit()">取消</button>
            </div>
        </td></tr>`;
    },

    toggleEditRow(orderId, rootId, event) {
        if (event) event.stopPropagation();
        if (this.editing && this.editing.orderId === orderId) {
            this.editing = null;
        } else {
            this.editing = { orderId: orderId, rootId: rootId };
        }
        this.render();
    },

    async saveEditRow() {
        const form = document.querySelector('.os-edit-form');
        if (!form) return;
        const data = {
            id: parseInt(form.dataset.id, 10),
            content: form.querySelector('.os-edit-content').value.trim(),
            dosage: form.querySelector('.os-edit-dosage').value.trim(),
            dosage_unit: form.querySelector('.os-edit-unit').value.trim(),
            usage_name: form.querySelector('.os-edit-usage').value,
            usage_rate: form.querySelector('.os-edit-rate').value.trim(),
            freq_code: form.querySelector('.os-edit-freq').value,
            freq_detail: form.querySelector('.os-edit-detail').value.trim(),
            billing_flags: Array.from(form.querySelectorAll('.os-edit-flag:checked')).map(c => c.value).join(','),
            order_period: form.querySelector('.os-edit-period').value
        };
        if (!data.content) { Dom.toast('医嘱内容不能为空', 'error'); return; }
        try {
            await AjaxLoader.api('orders/update', { method: 'POST', data: data });
            this.editing = null;
            Dom.toast('已保存', 'success');
            this.refresh();
        } catch (e) { /* 已提示 */ }
    },

    cancelEdit() {
        this.editing = null;
        this.render();
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

    openMedication(event) { if (this.patientId) OrderQuickAdd.open('medication', event); },
    openNonmedication(event) { if (this.patientId) OrderQuickAdd.open('nonmedication', event); },
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
