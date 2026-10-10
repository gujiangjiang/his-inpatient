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
            <label class="os-filter-radio"><input type="radio" name="os-status-filter" value="draft,pending,verified,executing,completed,stopped" onchange="OrderStation.setFilter('status', 'draft,pending,verified,executing,completed,stopped')">医嘱本</label>
            <label class="os-filter-radio"><input type="radio" name="os-status-filter" value="invalid" onchange="OrderStation.setFilter('status', 'invalid')">作废</label>
            <label class="os-filter-radio"><input type="radio" name="os-status-filter" value="stopped" onchange="OrderStation.setFilter('status', 'stopped')">停止</label>
            <label class="os-filter-radio"><input type="radio" name="os-status-filter" value="draft" onchange="OrderStation.setFilter('status', 'draft')">待完成</label>
        </span>
    </div>

    <div class="os-table-wrap">
        <table class="os-table">
            <colgroup>
                <col class="os-col-check"><col class="os-col-period"><col class="os-col-time">
                <col><col class="os-col-dosage"><col class="os-col-usage"><col class="os-col-freq"><col class="os-col-freqdetail">
                <col class="os-col-doctor"><col class="os-col-op">
            </colgroup>
            <thead>
                <tr>
                    <th class="os-col-check"></th>
                    <th class="os-col-period">长期<br>临时</th>
                    <th>开始时间</th>
                    <th>医嘱内容</th>
                    <th>剂量</th>
                    <th>途径</th>
                    <th>频次</th>
                    <th>执行时间</th>
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

    async refresh(feedback, focusId) {
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
            if (focusId) this.focusOrder(focusId);
            if (feedback) Dom.toast('已刷新 ' + Format.shortTime(new Date()) + ' · 共 ' + this.groups.length + ' 组医嘱', 'success');
        } catch (e) { /* 已提示 */ }
    },

    /**
     * 定位并闪烁新开/变更的医嘱 (整组高亮)
     */
    focusOrder(orderId) {
        const tbody = document.getElementById('os-tbody');
        if (!tbody) return;
        const tr = tbody.querySelector('tr[data-oid="' + orderId + '"]');
        if (!tr) return;
        tr.scrollIntoView({ block: 'center', behavior: 'smooth' });
        const rootId = tr.dataset.root;
        const rows = rootId ? tbody.querySelectorAll('tr[data-root="' + rootId + '"]') : [tr];
        rows.forEach(r => r.classList.add('os-row-flash'));
        setTimeout(() => {
            tbody.querySelectorAll('tr.os-row-flash').forEach(r => r.classList.remove('os-row-flash'));
        }, 1800);
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
        const editable = root.status === 'draft';
        let html = '';
        rows.forEach((o, i) => {
            const first = i === 0;
            const span = first ? ` rowspan="${size}"` : '';
            const statusCls = { draft: ' os-txt-draft', pending: ' os-txt-pending', invalid: ' os-txt-invalid', stopped: ' os-txt-stopped' }[o.status] || '';
            const clickable = editable ? ` onclick="OrderDetail.open(${o.id}, ${root.id}, event)" title="点击查看/修改"` : '';
            html += `<tr data-oid="${o.id}" data-root="${root.id}" class="${first ? 'os-group-start' : ''}${statusCls}${editable ? ' os-row-editable' : ''}">`;
            if (first) {
                html += `<td class="os-col-check"${span}><input type="checkbox" ${this.selected.has(root.id) ? 'checked' : ''} onchange="OrderStation.toggleSelect(${root.id}, this.checked)"></td>`;
                html += `<td class="os-col-period"${span}>${this.periodBadge(root.order_period, editable, root)}</td>`;
                // 时间列与组同高合并居中 (子医嘱不再错位)
                html += `<td class="os-col-time"${span}>${this.timeText(root)}</td>`;
            }
            html += `<td${clickable} title="${editable ? '点击修改' : ''}">${Format.escape(o.content)}${this.tags(o.billing_flags)}</td>`;
            html += `<td class="os-col-dosage"${clickable}>${Format.escape(o.dosage || '')}${o.dosage_unit ? ' ' + Format.escape(o.dosage_unit) : ''}</td>`;
            if (first) {
                const usageCls = size > 1 ? 'os-usage-brace' : 'os-usage-single';
                const editUsage = editable ? ` onclick="OrderStation.editCell('usage', ${root.id}, event)" title="点击修改途径"` : '';
                const editFreq = editable ? ` onclick="OrderStation.editCell('freq', ${root.id}, event)" title="点击修改频次"` : '';
                const editDetail = editable ? ` onclick="OrderStation.editCell('detail', ${root.id}, event)" title="点击修改执行时间"` : '';
                html += `<td class="os-col-usage ${usageCls}"${span}${editUsage}>${this.usageText(root, o)}</td>`;
                html += `<td class="os-col-freq"${span}${editFreq}>${Format.escape(root.freq_code || root.frequency || '')}</td>`;
                html += `<td class="os-col-freqdetail"${span}${editDetail}>${Format.escape(root.freq_detail || '')}</td>`;
                html += `<td class="os-col-doctor"${span}>${Format.escape(root.creator_name || '')}</td>`;
                html += `<td class="os-col-op"${span}><button type="button" class="os-op-btn" onclick="OrderActions.show(${root.id}, event)" title="操作">${svgIcon('common/actions/more', 16)}</button></td>`;
            }
            html += '</tr>';
        });
        return html;
    },

    periodBadge(period, editable, root) {
        const label = period === 'temp' ? '临' : '长';
        const cls = period === 'temp' ? 'os-period-temp' : 'os-period-long';
        if (!editable || !root) return `<span class="os-period ${cls}">${label}</span>`;
        return `<span class="os-period ${cls} os-period-toggle" title="点击切换长期/临时" onclick="OrderStation.togglePeriod(${root.id}, '${period}', event)">${label}</span>`;
    },

    /**
     * 点击长/临徽章快速切换并实时保存 (组内同步)
     */
    async togglePeriod(rootId, current, event) {
        if (event) event.stopPropagation();
        const root = this.groups.find(g => g.id == rootId);
        if (!root || root.status !== 'draft') return;
        const next = current === 'temp' ? 'long' : 'temp';
        const ids = [rootId].concat((root.sub_orders || []).map(s => s.id));
        try {
            await Promise.all(ids.map(id => AjaxLoader.api('orders/update', { method: 'POST', data: { id: id, order_period: next } })));
            Dom.toast('已切换为' + (next === 'temp' ? '临时' : '长期') + '医嘱', 'success');
            this.refresh(false, rootId);
        } catch (e) { /* 已提示 */ }
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
        const parts = [];
        if (usage) parts.push(Format.escape(usage));
        if (rate) parts.push(Format.escape(rate));
        return `<span class="os-usage-text">${parts.join(' ')}</span>`;
    },

    /** 频次对应的默认执行时间 (自动生成, 可手动修改) */
    FREQ_DETAILS: { qd: '07', bid: '07-16', tid: '07-11-16', qid: '07-11-16-21', qn: '20' },

    autoFreqDetail(freq) {
        return this.FREQ_DETAILS[freq] || '';
    },

    /**
     * 行内编辑途径/频次/执行时间 (仅草稿, 变更即实时保存)
     */
    editCell(kind, rootId, event) {
        if (event) event.stopPropagation();
        const td = event && event.currentTarget ? event.currentTarget : null;
        if (!td || td.querySelector('select,input')) return;
        const root = this.groups.find(g => g.id == rootId);
        if (!root || root.status !== 'draft') return;
        const orig = td.innerHTML;
        let done = false;
        const restore = () => { if (!done) { done = true; td.innerHTML = orig; } };
        const commit = async (fields) => {
            done = true;
            try {
                await AjaxLoader.api('orders/update', { method: 'POST', data: Object.assign({ id: rootId }, fields) });
                Dom.toast('已实时保存', 'success');
                this.refresh(false, rootId);
            } catch (e) { td.innerHTML = orig; }
        };

        if (kind === 'usage' || kind === 'freq') {
            const isUsage = kind === 'usage';
            const freqs = (typeof OrderEditor !== 'undefined') ? OrderEditor.FREQS : [['', '无']];
            const values = isUsage
                ? ['', '静脉输液', '静脉注射', '口服', '皮下注射', '肌肉注射', '外用', '雾化吸入', '护理', '测量', '饮食', '陪护']
                : freqs.map(f => f[0]);
            const labels = isUsage ? values : freqs.map(f => f[1]);
            const current = isUsage ? (root.usage_name || '') : (root.freq_code || root.frequency || '');
            td.innerHTML = '<select class="os-cell-select">' + values.map((v, i) =>
                `<option value="${v}" ${v === current ? 'selected' : ''}>${labels[i] || v || '-'}</option>`).join('') + '</select>';
            const sel = td.querySelector('select');
            sel.focus();
            sel.addEventListener('change', () => {
                if (isUsage) {
                    commit({ usage_name: sel.value });
                } else {
                    const fields = { freq_code: sel.value };
                    const auto = this.autoFreqDetail(sel.value);
                    if (auto) fields.freq_detail = auto;
                    commit(fields);
                }
            });
            sel.addEventListener('blur', () => setTimeout(restore, 150));
            sel.addEventListener('keydown', e => { if (e.key === 'Escape') { done = true; td.innerHTML = orig; } });
        } else {
            td.innerHTML = `<input class="os-cell-input" value="${Format.escape(root.freq_detail || '')}" placeholder="如 07-16">`;
            const input = td.querySelector('input');
            input.focus();
            input.addEventListener('keydown', e => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    done = true;
                    if (input.value.trim() !== (root.freq_detail || '')) commit({ freq_detail: input.value.trim() });
                    else restore();
                } else if (e.key === 'Escape') {
                    done = true;
                    td.innerHTML = orig;
                }
            });
            input.addEventListener('blur', () => setTimeout(() => {
                if (done) return;
                done = true;
                if (input.value.trim() !== (root.freq_detail || '')) commit({ freq_detail: input.value.trim() });
                else td.innerHTML = orig;
            }, 150));
        }
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
