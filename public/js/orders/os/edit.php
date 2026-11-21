<script>
/* 医嘱站·行内编辑与选择 (挂载到 OrderStation 单例): 途径/频次/执行时间/长临切换/勾选 */
Object.assign(OrderStation, {
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
            this.refresh();
        } catch (e) { /* 已提示 */ }
    },

    /**
     * 行内编辑途径/频次/执行时间 (仅草稿, 悬浮列表选择, 变更即实时保存)
     * 不替换单元格内容; 列表首项 「-」 清空, 搜索栏右侧 × 一键清空
     */
    USAGE_OPTIONS: ['', '静脉输液', '静脉滴注', '静脉注射', '口服', '皮下注射', '肌肉注射', '外用', '雾化吸入', '护理', '测量', '饮食', '陪护'],

    editCell(kind, rootId, event) {
        if (event) event.stopPropagation();
        const root = this.groups.find(g => g.id == rootId);
        if (!root || root.status !== 'draft') return;
        if ((kind === 'freq' || kind === 'detail') && root.order_period === 'temp') {
            Dom.toast('临时医嘱无频次/执行时间', 'info');
            return;
        }
        if (kind === 'usage' || kind === 'freq') {
            const isUsage = kind === 'usage';
            let options;
            let current;
            if (isUsage) {
                const values = this.USAGE_OPTIONS.slice();
                current = root.usage_name || '';
                // 当前值不在选项内 (如项目库同步的非常用途径) 时保留为可选项, 避免编辑丢失
                if (current && values.indexOf(current) === -1) values.splice(1, 0, current);
                options = values.map(v => [v, v]);
            } else {
                current = root.freq_code || root.frequency || '';
                options = this.FREQS.map(f => [f[0], f[1]]);
            }
            CellPicker.open({
                options: options,
                current: current,
                placeholder: isUsage ? '搜索途径…' : '搜索频次…',
                onPick: (val) => {
                    if (isUsage) this.commitCell(rootId, { usage_name: val });
                    else this.commitCell(rootId, { freq_code: val, freq_detail: this.autoFreqDetail(val) });
                }
            }, event);
        } else {
            CellPicker.open({
                type: 'input',
                current: root.freq_detail || '',
                placeholder: '如 07-16, 回车保存',
                onPick: (val) => {
                    const v = (val || '').trim();
                    if (v !== (root.freq_detail || '')) this.commitCell(rootId, { freq_detail: v });
                }
            }, event);
        }
    },

    /* 行内编辑提交 (变更即保存) */
    async commitCell(rootId, fields) {
        try {
            await AjaxLoader.api('orders/update', { method: 'POST', data: Object.assign({ id: rootId }, fields) });
            Dom.toast('已实时保存', 'success');
            this.refresh();
        } catch (e) { /* 已提示 */ }
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
});
</script>
