<script>
/* 医嘱站·列表渲染 (挂载到 OrderStation 单例): 组行构建/徽章/标签/定位闪烁 */
Object.assign(OrderStation, {
    /**
     * 定位并闪烁新开医嘱 (整组高亮, 仅用于新开)
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

    /* 列表底部加载提示 (全部加载完成才显示, 支持中滚动自动续载) */
    loadHintHtml() {
        if (this.groups.length === 0) return '';
        if (!this.total || this.groups.length >= this.total) {
            return '<tr class="os-load-end"><td colspan="10">已全部加载 · 共 ' + this.groups.length + ' 组医嘱</td></tr>';
        }
        return '';
    },

    render() {
        const tbody = document.getElementById('os-tbody');
        const empty = document.getElementById('os-empty');
        if (!tbody) return;
        empty.style.display = this.groups.length === 0 ? 'block' : 'none';
        tbody.innerHTML = this.groups.map(g => this.renderGroup(g)).join('') + this.loadHintHtml();
        this.updateSelectedHint();
    },

    renderGroup(root) {
        const rows = [root].concat(root.sub_orders || []);
        const size = rows.length;
        // 申请类自动医嘱 (检验/检查/会诊) 锁定行内编辑: 不可切换周期/修改剂量/途径/频次/执行时间
        const isAuto = root.is_auto_generated == 1;
        const editable = root.status === 'draft' && !isAuto;
        let html = '';
        rows.forEach((o, i) => {
            const first = i === 0;
            const span = first ? ` rowspan="${size}"` : '';
            const statusCls = { draft: ' os-txt-draft', pending: ' os-txt-pending', invalid_pending: ' os-txt-invalid_pending', stop_pending: ' os-txt-stop_pending', invalid: ' os-txt-invalid', stopped: ' os-txt-stopped' }[o.status] || '';
            const openLink = ` onclick="OrderDetail.open(${o.id}, ${root.id}, event)" title="点击查看医嘱详情"`;
            html += `<tr data-oid="${o.id}" data-root="${root.id}" class="${first ? 'os-group-start' : ''}${statusCls}${editable ? ' os-row-editable' : ''}">`;
            if (first) {
                html += `<td class="os-col-check"${span}><input type="checkbox" ${this.selected.has(root.id) ? 'checked' : ''} onchange="OrderStation.toggleSelect(${root.id}, this.checked)"></td>`;
                html += `<td class="os-col-period"${span}>${this.periodBadge(root.order_period, editable, root)}</td>`;
                // 时间列与组同高合并居中 (子医嘱不再错位)
                html += `<td class="os-col-time"${span}>${this.timeText(root)}</td>`;
            }
            // 仅医嘱内容文字可点击 (下划线提示), 其余为空白区域; 任意状态均可查看详情
            const canGroup = editable && first && root.category === 'medication';
            const canQuickDel = editable;
            const groupBtn = canGroup ? `<span class="os-row-act os-group-add" title="加入成组医嘱" onclick="OrderStation.addToGroup(${root.id}, event)">${svgIcon('common/actions/plus', 11)}</span>` : '';
            const delBtn = canQuickDel ? `<span class="os-row-act os-group-del" title="${first ? '删除整组医嘱' : '删除本条子医嘱'}" onclick="OrderStation.quickDelete(${o.id}, ${root.id}, ${first ? 1 : 0}, event)">${svgIcon('common/actions/close', 10)}</span>` : '';
            html += `<td><span class="os-content-link"${openLink}>${Format.escape(o.content)}</span>${o.notes ? ` <span class="os-note">（${Format.escape(o.notes)}）</span>` : ''}${groupBtn}${delBtn}${this.tags(o.billing_flags)}${first && root.consult_status === 'replied' ? '<span class="os-tag os-tag-consult">已会诊</span>' : ''}</td>`;
            const doseClick = editable ? ` onclick="DosePicker.open(${o.id}, ${root.id}, event)" title="点击选择剂量"` : '';
            html += `<td class="os-col-dosage os-dose-link"${doseClick}>${Format.escape(o.dosage || '')}${o.dosage_unit ? ' ' + Format.escape(o.dosage_unit) : ''}</td>`;
            if (first) {
                const usageCls = size > 1 ? 'os-usage-brace' : 'os-usage-single';
                const isTemp = root.order_period === 'temp';
                const editUsage = editable ? ` onclick="OrderStation.editCell('usage', ${root.id}, event)" title="点击修改途径"` : '';
                const editFreq = (editable && !isTemp) ? ` onclick="OrderStation.editCell('freq', ${root.id}, event)" title="点击修改频次"` : '';
                const editDetail = (editable && !isTemp) ? ` onclick="OrderStation.editCell('detail', ${root.id}, event)" title="点击修改执行时间"` : '';
                // 草稿未填写途径/频次时显示红色斜体占位 (提示可点击, 提交后自动隐藏)
                const hasUsage = !!(root.usage_name || root.usage_rate);
                const usageHtml = (editable && !hasUsage) ? '<span class="os-ph">【途径】</span>' : this.usageText(root, o);
                const freqVal = root.freq_code || root.frequency || '';
                const freqHtml = (editable && !isTemp && !freqVal) ? '<span class="os-ph">【频次】</span>' : Format.escape(freqVal);
                const editCls = editable ? ' os-cell-editable' : '';
                html += `<td class="os-col-usage ${usageCls}${editCls}"${span}${editUsage}>${usageHtml}</td>`;
                html += `<td class="os-col-freq${editCls}"${span}${editFreq} title="${isTemp ? '临时医嘱无频次' : ''}">${freqHtml}</td>`;
                html += `<td class="os-col-freqdetail${editCls}"${span}${editDetail} title="${isTemp ? '临时医嘱无执行时间' : ''}">${Format.escape(root.freq_detail || '')}</td>`;
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
    timeText(o) {
        const start = Format.shortTime(o.start_date || o.created_at);
        if (o.stop_date) {
            return `${start}<div style="font-size:11px;color:#909399">停 ${Format.shortTime(o.stop_date)}</div>`;
        }
        return start;
    },
});
</script>
