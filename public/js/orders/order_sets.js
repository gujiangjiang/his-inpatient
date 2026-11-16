// public/js/orders/order_sets.js - 医嘱组套(路径): 选择/勾选修改套用 + 新建/编辑编辑器
// 主模态框: 左(搜索+组套列表+新增/编辑) / 右(组套医嘱: 勾选/修改/成组/套用)
// 编辑器: 套餐名 + 药品/非药品加入 + 行内编辑(剂量/途径/频次/长临/备注/成组/删除)
const OrderSets = {
    overlay: null,
    sets: [],
    currentId: null,
    work: [],          // 右栏工作副本 [{...,_checked,_group}]
    search: '',
    editorOverlay: null,
    editorItems: [],   // 编辑器工作项
    editorId: null,

    FREQS: [
        ['', '无'], ['qd', 'qd 每日1次'], ['bid', 'bid 每日2次'], ['tid', 'tid 每日3次'],
        ['qid', 'qid 每日4次'], ['q6h', 'q6h 每6小时'], ['q8h', 'q8h 每8小时'],
        ['q12h', 'q12h 每12小时'], ['q2h', 'q2h 每2小时'], ['qn', 'qn 每晚'],
        ['prn', 'prn 必要时'], ['st', 'st 立即']
    ],
    USAGES: ['', '静脉输液', '静脉滴注', '静脉注射', '口服', '皮下注射', '肌肉注射', '外用', '雾化吸入', '护理', '测量', '饮食', '陪护'],

    freqDetailOf(freq) {
        if (typeof OrderStation !== 'undefined' && OrderStation.autoFreqDetail) return OrderStation.autoFreqDetail(freq);
        return { qd: '07', bid: '07-16', tid: '07-11-16', qid: '07-11-16-21', qn: '20' }[freq] || '';
    },

    /* ================= 主模态框 (两栏) ================= */
    async open() {
        const content = '<div class="osets">'
            + '<div class="osets-left">'
            + '<input type="text" class="form-control osets-search" id="osets-search" placeholder="搜索组套名称…">'
            + '<div class="osets-list" id="osets-list"><div class="osets-empty">加载中…</div></div>'
            + '<div class="osets-left-foot"><button type="button" class="btn btn-small btn-primary" id="osets-new">＋ 新增</button>'
            + '<button type="button" class="btn btn-small" id="osets-edit" style="display:none">编辑</button></div>'
            + '</div>'
            + '<div class="osets-right">'
            + '<div class="osets-right-head"><label class="osets-ck"><input type="checkbox" id="osets-all"> 全选</label>'
            + '<span class="osets-right-title" id="osets-title">组套医嘱</span><span class="osets-right-hint">勾选要开具的医嘱；可修改剂量/途径/频次/长临/备注与成组</span></div>'
            + '<div class="osets-items" id="osets-items"><div class="osets-empty">请从左侧选择组套</div></div>'
            + '<div class="osets-right-foot"><span class="osets-right-count" id="osets-count"></span>'
            + '<button type="button" class="btn btn-small btn-success" id="osets-apply" disabled>套用到医嘱</button></div>'
            + '</div></div>';
        this.overlay = Dom.modal('路径 / 组套', content, [{ text: '关闭', type: 'default' }]);
        const modal = this.overlay.querySelector('.modal');
        if (modal) modal.classList.add('modal-lg');

        const search = this.overlay.querySelector('#osets-search');
        search.addEventListener('input', () => { this.search = search.value.trim(); this.renderLeft(); });
        this.overlay.querySelector('#osets-new').addEventListener('click', (e) => this.openEditor(null, e));
        this.overlay.querySelector('#osets-edit').addEventListener('click', (e) => {
            if (this.currentId) this.openEditor(this.currentId, e);
        });
        this.overlay.querySelector('#osets-all').addEventListener('change', (e) => {
            this.work.forEach(w => { w._checked = e.target.checked; });
            this.renderRight();
        });
        this.overlay.querySelector('#osets-apply').addEventListener('click', () => this.apply());

        await this.reload();
        this.renderLeft();
        if (this.sets.length) this.select(this.sets[0].id);
        else this.renderRight();
    },

    async reload() {
        try {
            const r = await AjaxLoader.api('orders/set-list', { silent: true });
            this.sets = r.data || [];
        } catch (e) { this.sets = []; }
    },

    currentSet() { return this.sets.find(s => String(s.id) === String(this.currentId)) || null; },

    renderLeft() {
        const box = this.overlay && this.overlay.querySelector('#osets-list');
        if (!box) return;
        const kw = this.search.toLowerCase();
        const list = this.sets.filter(s => !kw || String(s.name).toLowerCase().indexOf(kw) >= 0 || String(s.creator_name || '').toLowerCase().indexOf(kw) >= 0);
        if (!list.length) {
            box.innerHTML = '<div class="osets-empty">' + (this.sets.length ? '无匹配组套' : '暂无组套，点击下方「＋ 新增」创建') + '</div>';
        } else {
            box.innerHTML = list.map(s => {
                const cat = s.category === 'medication' ? '药品' : (s.category === 'nonmedication' ? '非药品' : '混合');
                return '<div class="osets-row' + (String(s.id) === String(this.currentId) ? ' active' : '') + '" data-id="' + s.id + '">'
                    + '<span class="osets-row-name" title="' + Format.escape(s.name) + '">' + Format.escape(s.name) + '</span>'
                    + '<span class="os-tag os-tag-yufang">' + cat + '</span>'
                    + '<span class="osets-row-meta">' + s.item_count + ' 条</span></div>';
            }).join('');
            box.querySelectorAll('.osets-row').forEach(row => {
                row.addEventListener('click', () => this.select(+row.dataset.id));
            });
        }
        const editBtn = this.overlay.querySelector('#osets-edit');
        if (editBtn) {
            const s = this.currentSet();
            const mine = s && typeof Auth !== 'undefined' && Auth.user && (String(s.created_by) === String(Auth.user.id) || Auth.user.role === 'admin');
            editBtn.style.display = (s && mine) ? '' : 'none';
        }
    },

    select(id) {
        this.currentId = id;
        const set = this.currentSet();
        this.work = ((set && set.items) || []).map(it => ({
            category: it.category || 'nonmedication',
            drug_id: it.drug_id || null,
            content: it.content || '',
            dosage: it.dosage || '',
            dosage_unit: it.dosage_unit || '',
            usage_name: it.usage_name || '',
            usage_rate: it.usage_rate || '',
            freq_code: it.freq_code || '',
            freq_detail: it.freq_detail || '',
            order_period: it.order_period === 'temp' ? 'temp' : 'long',
            notes: it.notes || '',
            _checked: true,
            _group: (parseInt(it.group_seq, 10) || 0) > 0, /* 还原组套内已保存的成组标记 */
        }));
        this.renderLeft();
        this.renderRight();
    },

    renderRight() {
        const box = this.overlay && this.overlay.querySelector('#osets-items');
        if (!box) return;
        const set = this.currentSet();
        const title = this.overlay.querySelector('#osets-title');
        if (title) title.textContent = set ? ('组套医嘱 · ' + set.name) : '组套医嘱';
        const all = this.overlay.querySelector('#osets-all');
        const checkedCount = this.work.filter(w => w._checked).length;
        if (all) {
            all.checked = this.work.length > 0 && checkedCount === this.work.length;
            all.disabled = this.work.length === 0;
        }
        const applyBtn = this.overlay.querySelector('#osets-apply');
        if (applyBtn) {
            applyBtn.disabled = checkedCount === 0;
            applyBtn.textContent = '套用到医嘱（' + checkedCount + '）';
        }
        if (!set || !this.work.length) {
            box.innerHTML = '<div class="osets-empty">' + (set ? '该组套暂无内容' : '请从左侧选择组套') + '</div>';
            return;
        }
        box.innerHTML = '<table class="osets-table"><thead><tr>'
            + '<th style="width:34px"></th><th style="width:52px">长临</th><th>医嘱内容</th><th style="width:110px">剂量</th>'
            + '<th style="width:96px">途径</th><th style="width:72px">频次</th><th style="width:92px">执行时间</th>'
            + '<th style="width:120px">备注</th><th style="width:44px">成组</th><th style="width:30px"></th>'
            + '</tr></thead><tbody>'
            + this.work.map((w, i) => this.rowHtml(w, i, true)).join('')
            + '</tbody></table>';
        this.bindRows(box, this.work, () => this.renderRight());
    },

    rowHtml(w, i, withCheck) {
        const isTemp = w.order_period === 'temp';
        return '<tr data-i="' + i + '">'
            + (withCheck === false ? '' : '<td><input type="checkbox" class="osets-ck" ' + (w._checked ? 'checked' : '') + '></td>')
            + '<td><span class="os-period ' + (isTemp ? 'os-period-temp' : 'os-period-long') + ' osets-period" title="点击切换长期/临时">' + (isTemp ? '临' : '长') + '</span></td>'
            + '<td><span class="osets-content" title="' + Format.escape(w.content) + '">' + Format.escape(w.content) + '</span>'
            + (w.category === 'medication' ? ' <span class="os-tag os-tag-yufang">药</span>' : '') + '</td>'
            + '<td><input type="text" class="osets-input osets-dosage" value="' + Format.escape(w.dosage + (w.dosage_unit ? ' ' + w.dosage_unit : '')) + '" placeholder="如 1片"></td>'
            + '<td><span class="osets-cell osets-usage" title="点击修改途径">' + (Format.escape(w.usage_name) || '<i class="osets-ph">途径</i>') + '</span></td>'
            + '<td><span class="osets-cell osets-freq" title="' + (isTemp ? '临时医嘱无频次' : '点击修改频次') + '">' + (isTemp ? '—' : (Format.escape(w.freq_code) || '<i class="osets-ph">频次</i>')) + '</span></td>'
            + '<td><span class="osets-cell osets-detail" title="' + (isTemp ? '临时医嘱无执行时间' : '点击修改执行时间') + '">' + (isTemp ? '—' : (Format.escape(w.freq_detail) || '<i class="osets-ph">时间</i>')) + '</span></td>'
            + '<td><input type="text" class="osets-input osets-notes" value="' + Format.escape(w.notes) + '" placeholder="备注"></td>'
            + '<td><input type="checkbox" class="osets-group"' + (w._group ? ' checked' : '') + ' title="勾选后与其他勾选项成组开具"></td>'
            + '<td><span class="osets-del" title="移除">' + svgIcon('common/actions/close', 10) + '</span></td>'
            + '</tr>';
    },

    bindRows(box, list, rerender) {
        box.querySelectorAll('tr[data-i]').forEach(tr => {
            const i = +tr.dataset.i;
            const w = list[i];
            if (!w) return;
            const ck = tr.querySelector('.osets-ck');
            if (ck) ck.addEventListener('change', (e) => { w._checked = e.target.checked; rerender(); });
            tr.querySelector('.osets-period').addEventListener('click', () => { w.order_period = w.order_period === 'temp' ? 'long' : 'temp'; rerender(); });
            tr.querySelector('.osets-dosage').addEventListener('change', (e) => {
                const v = e.target.value.trim();
                const m = v.match(/^([\d.]+)\s*(.*)$/);
                w.dosage = m ? m[1] : v;
                w.dosage_unit = m ? (m[2] || '') : '';
            });
            tr.querySelector('.osets-notes').addEventListener('change', (e) => { w.notes = e.target.value.trim(); });
            tr.querySelector('.osets-group').addEventListener('change', (e) => { w._group = e.target.checked; });
            tr.querySelector('.osets-del').addEventListener('click', () => { list.splice(i, 1); rerender(); });
            /* 途径/频次/执行时间: 悬浮选择器 (与医嘱列表一致) */
            const usageCell = tr.querySelector('.osets-usage');
            usageCell.addEventListener('click', (e) => {
                const values = this.USAGES.slice();
                if (w.usage_name && values.indexOf(w.usage_name) === -1) values.splice(1, 0, w.usage_name);
                CellPicker.open({ options: values.map(v => [v, v]), current: w.usage_name, placeholder: '搜索途径…',
                    onPick: (v) => { w.usage_name = v; rerender(); } }, e);
            });
            const freqCell = tr.querySelector('.osets-freq');
            freqCell.addEventListener('click', (e) => {
                if (w.order_period === 'temp') { Dom.toast('临时医嘱无频次', 'info'); return; }
                CellPicker.open({ options: this.FREQS, current: w.freq_code, placeholder: '搜索频次…',
                    onPick: (v) => { w.freq_code = v; w.freq_detail = this.freqDetailOf(v); rerender(); } }, e);
            });
            const detailCell = tr.querySelector('.osets-detail');
            detailCell.addEventListener('click', (e) => {
                if (w.order_period === 'temp') { Dom.toast('临时医嘱无执行时间', 'info'); return; }
                CellPicker.open({ type: 'input', current: w.freq_detail, placeholder: '如 07-16, 回车保存',
                    onPick: (v) => { w.freq_detail = (v || '').trim(); rerender(); } }, e);
            });
        });
    },

    /** 套用: 仅勾选项 (含修改与成组), 生成草稿 */
    async apply() {
        if (typeof OrderStation === 'undefined' || !OrderStation.patientId) { Dom.toast('请先选择患者', 'error'); return; }
        const picked = this.work.filter(w => w._checked);
        if (!picked.length) { Dom.toast('请至少勾选一条医嘱', 'error'); return; }
        /* 成组: 勾了成组的项共享 group_seq=1 */
        const items = picked.map(w => ({
            category: w.category, drug_id: w.drug_id, content: w.content,
            dosage: w.dosage, dosage_unit: w.dosage_unit,
            usage_name: w.usage_name, usage_rate: w.usage_rate,
            freq_code: w.freq_code, freq_detail: w.freq_detail,
            order_period: w.order_period, notes: w.notes,
            group_seq: w._group ? 1 : 0,
        }));
        try {
            const r = await AjaxLoader.api('orders/set-apply', { method: 'POST', data: {
                set_id: this.currentId, patient_id: OrderStation.patientId, submit: 0, items: items
            } });
            Dom.toast('已套用组套，生成 ' + (r.data.count || 0) + ' 条草稿医嘱', 'success');
            this.close();
            OrderStation.refresh();
        } catch (e) { /* 已提示 */ }
    },

    close() {
        if (this.overlay && this.overlay.parentNode) this.overlay.parentNode.removeChild(this.overlay);
        this.overlay = null;
        this.work = [];
        this.currentId = null;
    },

    /* ================= 编辑器 (新增 / 编辑) ================= */
    openEditor(setId, event) {
        if (event) event.stopPropagation();
        const set = setId ? this.currentSet() : null;
        this.editorId = setId || null;
        this.editorItems = set ? (set.items || []).map(it => ({
            category: it.category || 'nonmedication',
            drug_id: it.drug_id || null,
            content: it.content || '',
            dosage: it.dosage || '', dosage_unit: it.dosage_unit || '',
            usage_name: it.usage_name || '', usage_rate: it.usage_rate || '',
            freq_code: it.freq_code || '', freq_detail: it.freq_detail || '',
            order_period: it.order_period === 'temp' ? 'temp' : 'long',
            notes: it.notes || '',
            _group: (parseInt(it.group_seq, 10) || 0) > 0,
        })) : [];

        const content = '<div class="osets-editor">'
            + '<div class="osets-editor-head">'
            + '<div class="form-group" style="margin:0;flex:1"><label class="form-label">套餐名</label>'
            + '<input type="text" class="form-control" id="osets-ed-name" placeholder="如：锁骨骨折术前医嘱" value="' + Format.escape(set ? set.name : '') + '"></div>'
            + '<div class="osets-editor-add"><button type="button" class="btn btn-small btn-primary" id="osets-ed-med">＋ 药品</button>'
            + '<button type="button" class="btn btn-small" id="osets-ed-non">＋ 非药品</button></div>'
            + '</div>'
            + '<div class="osets-editor-list" id="osets-ed-list"></div>'
            + '<div class="osets-editor-foot"><span class="osets-editor-hint">行内可改剂量/途径/频次/长临/备注；勾选「成组」的医嘱开具时作为一组</span>'
            + '<span class="osets-right-count" id="osets-ed-count"></span></div></div>';
        this.editorOverlay = Dom.modal(setId ? '编辑组套' : '新增组套', content, [
            { text: '保存', type: 'primary', onclick: () => this.saveEditor() },
            { text: '取消', type: 'default' }
        ]);
        const modal = this.editorOverlay.querySelector('.modal');
        if (modal) modal.classList.add('modal-lg');
        this.editorOverlay.querySelector('#osets-ed-med').addEventListener('click', (e) => this.editorAdd('medication', e));
        this.editorOverlay.querySelector('#osets-ed-non').addEventListener('click', (e) => this.editorAdd('nonmedication', e));
        this.renderEditor();
    },

    editorAdd(mode, event) {
        OrderQuickAdd.openPick(mode, event, (item) => {
            this.editorItems.push(Object.assign({}, item, {
                order_period: 'long', freq_detail: item.freq_detail || '', notes: '', _group: false,
                dosage_unit: item.dosage_unit || ''
            }));
            this.renderEditor();
        });
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
        box.innerHTML = '<table class="osets-table"><thead><tr>'
            + '<th style="width:52px">长临</th><th>医嘱内容</th><th style="width:110px">剂量</th>'
            + '<th style="width:96px">途径</th><th style="width:72px">频次</th><th style="width:92px">执行时间</th>'
            + '<th style="width:120px">备注</th><th style="width:44px">成组</th><th style="width:30px"></th>'
            + '</tr></thead><tbody>'
            + this.editorItems.map((it, i) => this.rowHtml(it, i, false)).join('')
            + '</tbody></table>';
        this.bindRows(box, this.editorItems, () => this.renderEditor());
    },

    async saveEditor() {
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
            group_seq: it._group ? 1 : 0,
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
};

window.OrderSets = OrderSets;
