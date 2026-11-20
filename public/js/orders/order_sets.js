// public/js/orders/order_sets.js - 医嘱组套(路径): 选择/勾选修改套用 + 新建/编辑编辑器
// 主模态框: 左(搜索+组套列表+新增/编辑) / 右(组套医嘱: 只读预览 + 勾选套用)
// 编辑器: 套餐名 + 药品/非药品加入 + 行内编辑(剂量/途径/频次/长临/备注/成组/删除)
const OrderSets = {
    overlay: null,
    sets: [],
    currentId: null,
    work: [],          // 右栏工作副本 [{...,_checked, group_seq, avail}]
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
        /* 重入守卫: 弹窗已打开时忽略再次触发 (原二次 open 会覆盖 this.overlay 且双弹窗并发 reload) */
        if (this.overlay && document.body.contains(this.overlay)) return;
        const content = '<div class="osets">'
            + '<div class="osets-left">'
            + '<input type="text" class="form-control osets-search" id="osets-search" placeholder="搜索组套名称…">'
            + '<div class="osets-list" id="osets-list"><div class="osets-empty">加载中…</div></div>'
            + '<div class="osets-left-foot"><button type="button" class="btn btn-small btn-primary" id="osets-new">＋ 新增</button>'
            + '<button type="button" class="btn btn-small" id="osets-edit" style="display:none">编辑</button>'
            + '<button type="button" class="btn btn-small btn-danger" id="osets-del" style="display:none">删除</button></div>'
            + '</div>'
            + '<div class="osets-right">'
            + '<div class="osets-right-head"><label class="osets-ck"><input type="checkbox" id="osets-all"> 全选</label>'
            + '<span class="osets-right-title" id="osets-title">组套医嘱</span><span class="osets-right-hint">勾选要开具的医嘱；内容与用法请到左侧「编辑」中修改</span></div>'
            + '<div class="osets-items" id="osets-items"><div class="osets-empty">请从左侧选择组套</div></div>'
            + '<div class="osets-right-foot"><span class="osets-right-count" id="osets-count"></span>'
            + '<button type="button" class="btn btn-small btn-success" id="osets-apply" disabled>套用到医嘱</button></div>'
            + '</div></div>';
        this.overlay = Dom.modal('路径 / 组套', content, [{ text: '关闭', type: 'default' }]);
        const modal = this.overlay.querySelector('.modal');
        if (modal) { modal.classList.add('modal-lg'); modal.classList.add('osets-modal'); }

        const search = this.overlay.querySelector('#osets-search');
        search.addEventListener('input', () => { this.search = search.value.trim(); this.renderLeft(); });
        this.overlay.querySelector('#osets-new').addEventListener('click', (e) => this.openEditor(null, e));
        this.overlay.querySelector('#osets-edit').addEventListener('click', (e) => {
            if (this.currentId) this.openEditor(this.currentId, e);
        });
        this.overlay.querySelector('#osets-del').addEventListener('click', (e) => {
            e.stopPropagation();
            this.confirmDelete();
        });
        this.overlay.querySelector('#osets-all').addEventListener('change', (e) => {
            this.work.forEach(w => {
                if (w.avail && w.avail.ok === false) { w._checked = false; return; }
                w._checked = e.target.checked;
            });
            this.renderRight();
        });
        this.overlay.querySelector('#osets-apply').addEventListener('click', () => this.apply());

        await this.reload();
        this.renderLeft();
        if (this.sets.length) this.select(this.sets[0].id);
        else this.renderRight();
    },

    /** 删除当前组套 (确认后执行) */
    confirmDelete() {
        const set = this.currentSet();
        if (!set) return;
        Dom.confirm('删除确认', '确认删除组套「' + Format.escape(set.name) + '」？（条目一并删除，不可恢复）', async () => {
            try {
                await AjaxLoader.api('orders/set-delete', { method: 'POST', data: { id: set.id } });
                Dom.toast('已删除组套', 'success');
                this.currentId = null;
                this.work = [];
                await this.reload();
                this.renderLeft();
                if (this.sets.length) this.select(this.sets[0].id);
                else this.renderRight();
            } catch (err) { /* 已提示 */ }
        }, { danger: true, okText: '删除' });
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
                const badTag = s.invalid_count ? '<span class="osets-bad" title="存在已下架/变更的医嘱，需编辑更新">' + s.invalid_count + ' 项变更</span>' : '';
                return '<div class="osets-row' + (String(s.id) === String(this.currentId) ? ' active' : '') + '" data-id="' + s.id + '">'
                    + '<span class="osets-row-name" title="' + Format.escape(s.name) + '">' + Format.escape(s.name) + '</span>'
                    + badTag
                    + '<span class="osets-row-meta">' + s.item_count + ' 条</span></div>';
            }).join('');
            box.querySelectorAll('.osets-row').forEach(row => {
                row.addEventListener('click', () => this.select(+row.dataset.id));
            });
        }
        const editBtn = this.overlay.querySelector('#osets-edit');
        const delBtn = this.overlay.querySelector('#osets-del');
        const s = this.currentSet();
        const mine = s && typeof Auth !== 'undefined' && Auth.user && (String(s.created_by) === String(Auth.user.id) || Auth.user.role === 'admin');
        if (editBtn) editBtn.style.display = (s && mine) ? '' : 'none';
        if (delBtn) delBtn.style.display = (s && mine) ? '' : 'none';
    },

    select(id) {
        this.currentId = id;
        const set = this.currentSet();
        this.work = ((set && set.items) || []).map(it => ({
            category: it.category || 'nonmedication',
            drug_id: it.drug_id || null,
            ref_id: it.ref_id || null,
            ref_name: it.ref_name || '',
            ref_spec: it.ref_spec || '',
            ref_manufacturer: it.ref_manufacturer || '',
            content: it.content || '',
            dosage: it.dosage || '',
            dosage_unit: it.dosage_unit || '',
            usage_name: it.usage_name || '',
            usage_rate: it.usage_rate || '',
            freq_code: it.freq_code || '',
            freq_detail: it.freq_detail || '',
            order_period: it.order_period === 'temp' ? 'temp' : 'long',
            notes: it.notes || '',
            group_seq: parseInt(it.group_seq, 10) || 0,
            avail: it.avail || { ok: true, reason: '', detail: '' },
            _checked: !it.avail || it.avail.ok !== false, /* 下架/变更项默认不可选 */
        }));
        this.renderLeft();
        this.renderRight();
    },

    /* ---------- 组结构工具 (相同 group_seq>0 为一组, 首个为主医嘱) ---------- */
    groupLeaderIndex(list, i) {
        const g = list[i] && list[i].group_seq;
        if (!g) return -1;
        for (let k = 0; k <= i; k++) if (list[k].group_seq === g) return k;
        return -1;
    },
    groupMembers(list, leaderIdx) {
        const g = list[leaderIdx] && list[leaderIdx].group_seq;
        if (!g) return [];
        const out = [];
        list.forEach((w, i) => { if (w.group_seq === g) out.push(i); });
        return out;
    },

    renderRight() {
        const box = this.overlay && this.overlay.querySelector('#osets-items');
        if (!box) return;
        const set = this.currentSet();
        const title = this.overlay.querySelector('#osets-title');
        if (title) title.textContent = set ? ('组套医嘱 · ' + set.name) : '组套医嘱';
        const all = this.overlay.querySelector('#osets-all');
        const checkedCount = this.work.filter(w => w._checked).length;
        const selectable = this.work.filter(w => !(w.avail && w.avail.ok === false));
        const countEl = this.overlay.querySelector('#osets-count');
        if (countEl) countEl.textContent = '共 ' + this.work.length + ' 条' + (selectable.length < this.work.length ? '（' + (this.work.length - selectable.length) + ' 条不可选）' : '');
        if (all) {
            all.checked = selectable.length > 0 && selectable.every(w => w._checked);
            all.disabled = selectable.length === 0;
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
        box.innerHTML = '<table class="osets-table osets-apply-table"><colgroup>'
            + '<col style="width:36px"><col style="width:52px"><col><col style="width:92px"><col style="width:86px">'
            + '<col style="width:66px"><col style="width:86px"><col style="width:100px"></colgroup>'
            + '<thead><tr><th></th><th>长临</th><th>医嘱内容</th><th>剂量</th><th>途径</th><th>频次</th><th>执行时间</th><th>备注</th></tr></thead><tbody>'
            + this.work.map((w, i) => this.applyRowHtml(w, i)).join('')
            + '</tbody></table>';
        this.bindApplyRows(box);
    },

    applyRowHtml(w, i) {
        const isTemp = w.order_period === 'temp';
        const bad = w.avail && w.avail.ok === false;
        const leader = this.groupLeaderIndex(this.work, i);
        const isSub = leader >= 0 && leader !== i;
        const groupHint = isSub ? '<span class="osets-sub-caret">' + svgIcon('common/arrows/caret-right', 10) + '</span>' : '';
        const badTag = bad ? '<span class="osets-bad" title="' + Format.escape(w.avail.detail || w.avail.reason) + '">' + Format.escape(w.avail.reason) + '</span>' : '';
        return '<tr data-i="' + i + '"' + (bad ? ' class="osets-row-bad"' : '') + (isSub ? ' data-sub="1"' : '') + '>'
            + '<td><input type="checkbox" class="osets-ck"' + (w._checked ? ' checked' : '') + (bad ? ' disabled' : '') + '></td>'
            + '<td><span class="os-period ' + (isTemp ? 'os-period-temp' : 'os-period-long') + '">' + (isTemp ? '临' : '长') + '</span></td>'
            + '<td><div class="osets-line' + (isSub ? ' osets-sub-content' : '') + '">'
            + groupHint + '<span class="osets-line-name" title="' + Format.escape(w.content) + '">' + Format.escape(w.content) + '</span>'
            + (w.category === 'medication' ? '<span class="os-tag os-tag-yufang">药</span>' : '') + badTag + '</div></td>'
            + '<td>' + Format.escape((w.dosage || '') + (w.dosage_unit ? ' ' + w.dosage_unit : '')) + '</td>'
            + '<td>' + (isTemp ? '' : Format.escape(w.usage_name || '')) + '</td>'
            + '<td>' + (isTemp ? '—' : Format.escape(w.freq_code || '')) + '</td>'
            + '<td>' + (isTemp ? '—' : Format.escape(w.freq_detail || '')) + '</td>'
            + '<td>' + Format.escape(w.notes || '') + '</td>'
            + '</tr>';
    },

    /** 勾选联动: 取消主医嘱 → 组内子医嘱一并取消; 勾选子医嘱 → 主医嘱自动勾选 */
    bindApplyRows(box) {
        box.querySelectorAll('tr[data-i]').forEach(tr => {
            const i = +tr.dataset.i;
            const w = this.work[i];
            if (!w) return;
            tr.querySelector('.osets-ck').addEventListener('change', (e) => {
                const checked = e.target.checked;
                w._checked = checked;
                const leader = this.groupLeaderIndex(this.work, i);
                if (leader === i) {
                    /* 主医嘱: 联动组内全部 */
                    this.groupMembers(this.work, i).forEach(k => { this.work[k]._checked = checked; });
                } else if (leader >= 0) {
                    /* 子医嘱: 勾选则主医嘱随之勾选 */
                    if (checked) this.work[leader]._checked = true;
                }
                this.renderRight();
            });
        });
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
            tr.querySelector('.osets-del').addEventListener('click', () => { this.removeEditorItem(list, i); rerender(); });
            const plus = tr.querySelector('.osets-subadd');
            if (plus) plus.addEventListener('click', (e) => {
                e.stopPropagation();
                OrderQuickAdd.openPick('medication', e, (item) => this.addSub(list, i, item));
            });
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
        /* 成组: 每组 (主+勾选子) 分配一个递增 group_seq; 独立项 0; 下架/变更项兜底过滤 */
        const seqMap = {};
        let seq = 0;
        const items = this.work.map((w, i) => {
            if (!w._checked || (w.avail && w.avail.ok === false)) return null;
            const leader = this.groupLeaderIndex(this.work, i);
            let gseq = 0;
            if (leader >= 0) {
                if (seqMap[leader] == null) seqMap[leader] = ++seq;
                gseq = seqMap[leader];
            }
            return {
                category: w.category, drug_id: w.drug_id, content: w.content,
                dosage: w.dosage, dosage_unit: w.dosage_unit,
                usage_name: w.usage_name, usage_rate: w.usage_rate,
                freq_code: w.freq_code, freq_detail: w.freq_detail,
                order_period: w.order_period, notes: w.notes,
                group_seq: gseq,
            };
        }).filter(Boolean);
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
};

window.OrderSets = OrderSets;
