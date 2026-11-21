/* 医嘱组套·编辑器模态 (挂载到 OrderSets 单例): 行内编辑/加子医嘱/保存 */
Object.assign(OrderSets, {
    /* ================= 编辑器 (新增 / 编辑) ================= */
    openEditor(setId, event) {
        if (event) event.stopPropagation();
        const set = setId ? this.currentSet() : null;
        this.editorId = setId || null;
        this.editorItems = set ? (set.items || []).map(it => ({
            category: it.category || 'nonmedication',
            drug_id: it.drug_id || null,
            ref_id: it.ref_id || null, ref_name: it.ref_name || '',
            ref_spec: it.ref_spec || '', ref_manufacturer: it.ref_manufacturer || '',
            content: it.content || '',
            dosage: it.dosage || '', dosage_unit: it.dosage_unit || '',
            usage_name: it.usage_name || '', usage_rate: it.usage_rate || '',
            freq_code: it.freq_code || '', freq_detail: it.freq_detail || '',
            order_period: it.order_period === 'temp' ? 'temp' : 'long',
            notes: it.notes || '',
            group_seq: parseInt(it.group_seq, 10) || 0,
            avail: it.avail || { ok: true, reason: '', detail: '' },
        })) : [];

        const content = '<div class="osets-editor">'
            + '<div class="osets-editor-head">'
            + '<div class="form-group" style="margin:0;flex:1"><label class="form-label">套餐名</label>'
            + '<input type="text" class="form-control" id="osets-ed-name" placeholder="如：锁骨骨折术前医嘱" value="' + Format.escape(set ? set.name : '') + '"></div>'
            + '<div class="osets-editor-add"><button type="button" class="btn btn-small btn-primary" id="osets-ed-med">＋ 药品</button>'
            + '<button type="button" class="btn btn-small" id="osets-ed-non">＋ 非药品</button></div>'
            + '</div>'
            + '<div class="osets-editor-list" id="osets-ed-list"></div>'
            + '<div class="osets-editor-foot"><span class="osets-editor-hint">行内可改剂量/途径/频次/长临/备注；药品行「＋」可加入成组子的子医嘱，开具时同组联动</span>'
            + '<span class="osets-right-count" id="osets-ed-count"></span></div></div>';
        this.editorOverlay = Dom.modal(setId ? '编辑组套' : '新增组套', content, [
            { text: '保存', type: 'primary', onclick: () => this.saveEditor() },
            { text: '取消', type: 'default' }
        ]);
        const modal = this.editorOverlay.querySelector('.modal');
        if (modal) { modal.classList.add('modal-lg'); modal.classList.add('osets-modal'); }
        this.editorOverlay.querySelector('#osets-ed-med').addEventListener('click', (e) => this.editorAdd('medication', e));
        this.editorOverlay.querySelector('#osets-ed-non').addEventListener('click', (e) => this.editorAdd('nonmedication', e));
        this.renderEditor();
    },

    editorAdd(mode, event) {
        OrderQuickAdd.openPick(mode, event, (item) => {
            this.editorItems.push(Object.assign({}, item, {
                order_period: 'long', freq_detail: item.freq_detail || '', notes: '', group_seq: 0,
                dosage_unit: item.dosage_unit || '',
                avail: { ok: true, reason: '', detail: '' },
            }));
            this.renderEditor();
        });
    },

    /** 「+」加入成组子医嘱 (仅药品): 分配/复用组的 group_seq, 插入到该组末尾 */
    addSub(list, leaderIdx, item) {
        const leader = list[leaderIdx];
        if (!leader) return;
        if (leader.group_seq === 0) {
            let max = 0;
            list.forEach(w => { if ((w.group_seq || 0) > max) max = w.group_seq; });
            leader.group_seq = max + 1;
        }
        const seq = leader.group_seq;
        let insertAt = leaderIdx + 1;
        list.forEach((w, k) => { if (w.group_seq === seq && k >= insertAt) insertAt = k + 1; });
        list.splice(insertAt, 0, Object.assign({}, item, {
            order_period: leader.order_period === 'temp' ? 'temp' : (item.order_period || 'long'),
            freq_detail: item.freq_detail || '',
            notes: '',
            group_seq: seq,
            dosage_unit: item.dosage_unit || '',
            avail: { ok: true, reason: '', detail: '' },
        }));
        this.renderEditor();
    },

    /** 删除编辑器条目: 主医嘱连同其组内子医嘱一并删除 */
    removeEditorItem(list, i) {
        const leader = this.groupLeaderIndex(list, i);
        if (leader === i) {
            const members = this.groupMembers(list, i);
            members.sort((a, b) => b - a).forEach(k => list.splice(k, 1));
            return;
        }
        list.splice(i, 1);
    },

    renderEditor() {
        const box = this.editorOverlay && this.editorOverlay.querySelector('#osets-ed-list');
        if (!box) return;
        const cnt = this.editorOverlay.querySelector('#osets-ed-count');
        if (cnt) cnt.textContent = this.editorItems.length + ' 条';
        if (!this.editorItems.length) {
            box.innerHTML = '<div class="osets-empty">暂无医嘱，点击右上角「＋ 药品 / ＋ 非药品」加入</div>';
            return;
        }
        const w = this;
        box.innerHTML = '<table class="osets-table"><colgroup>'
            + '<col style="width:52px"><col><col style="width:92px"><col style="width:86px"><col style="width:66px">'
            + '<col style="width:86px"><col style="width:100px"><col style="width:36px"><col style="width:30px"></colgroup>'
            + '<thead><tr><th>长临</th><th>医嘱内容</th><th>剂量</th><th>途径</th><th>频次</th><th>执行时间</th><th>备注</th><th></th><th></th></tr></thead><tbody>'
            + this.editorItems.map((it, i) => this.editorRowHtml(it, i)).join('')
            + '</tbody></table>';
        this.bindRows(box, this.editorItems, () => this.renderEditor());
    },

    editorRowHtml(w, i) {
        const isTemp = w.order_period === 'temp';
        const leader = this.groupLeaderIndex(this.editorItems, i);
        const isSub = leader >= 0 && leader !== i;
        /* 「+」加子医嘱: 仅药品主医嘱 (与开医嘱一致) */
        const plusBtn = (!isSub && w.category === 'medication')
            ? '<span class="os-row-act os-group-add osets-subadd" title="加入成组医嘱（子医嘱，仅药品）">' + svgIcon('common/actions/plus', 11) + '</span>'
            : '';
        const glyph = isSub ? '<span class="osets-sub-caret">' + svgIcon('common/arrows/caret-right', 10) + '</span>' : '';
        const bad = w.avail && w.avail.ok === false;
        const badTag = bad ? '<span class="osets-bad" title="' + Format.escape(w.avail.detail || w.avail.reason) + '">' + Format.escape(w.avail.reason) + '</span>' : '';
        return '<tr data-i="' + i + '"' + (isSub ? ' data-sub="1"' : '') + (bad ? ' class="osets-row-bad"' : '') + '>'
            + '<td><span class="os-period ' + (isTemp ? 'os-period-temp' : 'os-period-long') + ' osets-period" title="点击切换长期/临时">' + (isTemp ? '临' : '长') + '</span></td>'
            + '<td><div class="osets-line' + (isSub ? ' osets-sub-content' : '') + '">'
            + glyph + '<span class="osets-line-name osets-content" title="' + Format.escape(w.content) + '">' + Format.escape(w.content) + '</span>'
            + (w.category === 'medication' ? '<span class="os-tag os-tag-yufang">药</span>' : '') + badTag + '</div></td>'
            + '<td><input type="text" class="osets-input osets-dosage" value="' + Format.escape(w.dosage + (w.dosage_unit ? ' ' + w.dosage_unit : '')) + '" placeholder="如 1片"></td>'
            + '<td><span class="osets-cell osets-usage" title="点击修改途径">' + (Format.escape(w.usage_name) || '<i class="osets-ph">途径</i>') + '</span></td>'
            + '<td><span class="osets-cell osets-freq" title="' + (isTemp ? '临时医嘱无频次' : '点击修改频次') + '">' + (isTemp ? '—' : (Format.escape(w.freq_code) || '<i class="osets-ph">频次</i>')) + '</span></td>'
            + '<td><span class="osets-cell osets-detail" title="' + (isTemp ? '临时医嘱无执行时间' : '点击修改执行时间') + '">' + (isTemp ? '—' : (Format.escape(w.freq_detail) || '<i class="osets-ph">时间</i>')) + '</span></td>'
            + '<td><input type="text" class="osets-input osets-notes" value="' + Format.escape(w.notes) + '" placeholder="备注"></td>'
            + '<td>' + plusBtn + '</td>'
            + '<td><span class="osets-del" title="移除">' + svgIcon('common/actions/close', 10) + '</span></td>'
            + '</tr>';
    },

    async saveEditor() {
        /* 组序号归一 (0=独立; 组从 1 起连续) */
        const map = {}; let seqN = 0;
        this.editorItems.forEach(w => {
            const g = w.group_seq || 0;
            if (!g) { w.group_seq = 0; return; }
            if (!map[g]) map[g] = ++seqN;
            w.group_seq = map[g];
        });
        const nameEl = this.editorOverlay && this.editorOverlay.querySelector('#osets-ed-name');
        const name = (nameEl ? nameEl.value : '').trim();
        if (!name) { Dom.toast('请输入套餐名', 'error'); return false; }
        if (!this.editorItems.length) { Dom.toast('请至少加入一条医嘱', 'error'); return false; }
        const allMed = this.editorItems.every(it => it.category === 'medication');
        const cat = allMed ? 'medication' : 'nonmedication';
        const items = this.editorItems.map(it => ({
            category: it.category, drug_id: it.drug_id, content: it.content,
            dosage: it.dosage, dosage_unit: it.dosage_unit,
            usage_name: it.usage_name, usage_rate: it.usage_rate,
            freq_code: it.freq_code, freq_detail: it.freq_detail,
            order_period: it.order_period, notes: it.notes,
            group_seq: it.group_seq || 0,
            ref_id: it.ref_id || null, ref_name: it.ref_name || '',
            ref_spec: it.ref_spec || '', ref_manufacturer: it.ref_manufacturer || '',
        }));
        try {
            const r = await AjaxLoader.api('orders/set-save', { method: 'POST', data: {
                id: this.editorId || undefined, name: name, category: cat, items: items
            } });
            Dom.toast('组套已保存', 'success');
            this.closeEditor();
            await this.reload();
            this.select(r.data.id); /* 保存后定位到该组套 */
        } catch (e) { return false; }
        return true;
    },

    closeEditor() {
        if (this.editorOverlay && this.editorOverlay.parentNode) this.editorOverlay.parentNode.removeChild(this.editorOverlay);
        this.editorOverlay = null;
        this.editorItems = [];
        this.editorId = null;
    },
});
