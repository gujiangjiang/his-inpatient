<?php header("Content-Type: text/html; charset=utf-8"); ?>
<div class="order-station">
    <div class="os-toolbar">
        <button type="button" class="os-tool" onclick="OrderStation.openMedication(event)">＋ 药品<span class="key">F9</span></button>
        <button type="button" class="os-tool" onclick="OrderStation.openNonmedication(event)">＋ 非药品<span class="key">F8</span></button>
        <span class="os-sep"></span>
        <button type="button" class="os-tool os-tool-success" onclick="OrderStation.submitAllDrafts()">提交<span class="key">F2</span></button>
        <button type="button" class="os-tool os-tool-danger" onclick="OrderStation.stopAllLong()">长期全停<span class="key">F4</span></button>
        <button type="button" class="os-tool" onclick="OrderStation.refresh(true)">刷新<span class="key">F5</span></button>
        <span class="os-sep"></span>
        <button type="button" class="os-tool" onclick="OrderStation.openSetPicker()">路径/组套</button>
        <button type="button" class="os-tool" onclick="OrderStation.toggleAssist()">辅助决策</button>
        <button type="button" class="os-tool" onclick="OrderActions.showQuickActions(event)">快速操作</button>
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
            <input type="text" id="os-date-from" class="os-date-input" data-dtpicker="date" placeholder="开始日期" onchange="OrderStation.setFilter('date_from', this.value)">
            至
            <input type="text" id="os-date-to" class="os-date-input" data-dtpicker="date" placeholder="结束日期" onchange="OrderStation.setFilter('date_to', this.value)">
        </span>
        <span class="os-sep"></span>
        <span class="os-filter-group">
            状态
            <label class="os-filter-radio"><input type="radio" name="os-status-filter" value="" checked onchange="OrderStation.setFilter('status', '')">全部</label>
            <label class="os-filter-radio"><input type="radio" name="os-status-filter" value="draft,pending,invalid_pending,stop_pending,verified,executing,completed,stopped" onchange="OrderStation.setFilter('status', 'draft,pending,invalid_pending,stop_pending,verified,executing,completed,stopped')">医嘱本</label>
            <label class="os-filter-radio"><input type="radio" name="os-status-filter" value="invalid,invalid_pending" onchange="OrderStation.setFilter('status', 'invalid,invalid_pending')">作废</label>
            <label class="os-filter-radio"><input type="radio" name="os-status-filter" value="stopped,stop_pending" onchange="OrderStation.setFilter('status', 'stopped,stop_pending')">停止</label>
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
    FREQS: [
        ['', '无'], ['qd', 'qd 每日1次'], ['bid', 'bid 每日2次'], ['tid', 'tid 每日3次'],
        ['qid', 'qid 每日4次'], ['q6h', 'q6h 每6小时'], ['q8h', 'q8h 每8小时'],
        ['q12h', 'q12h 每12小时'], ['q2h', 'q2h 每2小时'], ['qn', 'qn 每晚'],
        ['prn', 'prn 必要时'], ['st', 'st 立即']
    ],
    filters: { period: '', status: '', date_from: '', date_to: '' },
    groups: [],
    pageSize: 50,        // 动态加载: 每页医嘱组数
    pageCount: 1,        // 已加载页数
    total: 0,            // 服务端总数 (组)
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
        this.bindGroupHover();
        this.bindInfiniteScroll();
        // 初始化私有日期选择器 (筛选日期范围)
        if (typeof DateTimePicker !== 'undefined') DateTimePicker.initInputs(document.getElementById('app-content'));
        this.fillAssist(ctx);
        this.refresh();
    },

    /* 医嘱列表动态滚动加载 (住院长列表: 触底续载, 避免一次性拉取几千组) */
    bindInfiniteScroll() {
        const wrap = document.querySelector('.os-table-wrap');
        if (!wrap || wrap._osScrollBound) return;
        wrap._osScrollBound = true;
        this._scroll = ScrollLoad.attach(wrap, () => this.loadMoreGroups());
    },

    /* 整组联动悬停: 鼠标悬停组内任意行, 主/子医嘱同时高亮 */
    bindGroupHover() {
        const tbody = document.getElementById('os-tbody');
        if (!tbody || tbody._hoverBound) return;
        tbody._hoverBound = true;
        tbody.addEventListener('mouseover', (e) => {
            const tr = e.target.closest('tr[data-root]');
            if (!tr) return;
            const root = tr.getAttribute('data-root');
            if (this._hoverRoot === root) return;
            this._hoverRoot = root;
            tbody.querySelectorAll('tr[data-root]').forEach(x =>
                x.classList.toggle('os-row-hover', x.getAttribute('data-root') === root));
        });
        tbody.addEventListener('mouseleave', () => this.clearGroupHover());
    },

    clearGroupHover() {
        this._hoverRoot = null;
        document.querySelectorAll('#os-tbody tr.os-row-hover').forEach(x => x.classList.remove('os-row-hover'));
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
            else if (e.key === 'F2') { e.preventDefault(); this.submitAllDrafts(); }
            else if (e.key === 'F4') { e.preventDefault(); this.stopAllLong(); }
            else if (e.key === 'F5') { e.preventDefault(); this.refresh(); }
        };
        document.addEventListener('keydown', this.keyHandler);
    },

    setFilter(key, value) {
        this.filters[key] = value;
        this.refresh();
    },

    filterQuery() {
        const qs = new URLSearchParams({ patient_id: this.patientId, per_page: this.pageSize });
        if (this.filters.period) qs.set('order_period', this.filters.period);
        if (this.filters.status) qs.set('status', this.filters.status);
        if (this.filters.date_from) qs.set('date_from', this.filters.date_from);
        if (this.filters.date_to) qs.set('date_to', this.filters.date_to);
        return qs;
    },

    fetchGroupsPage(page) {
        const qs = this.filterQuery();
        qs.set('page', page);
        return AjaxLoader.api('orders/list?' + qs.toString(), { silent: true }).catch(() => null);
    },

    /* 触底加载下一页 (ScrollLoad 回调, 返回是否还有更多) */
    async loadMoreGroups() {
        if (!this.total || this.groups.length >= this.total) return false;
        const next = this.pageCount + 1;
        const r = await this.fetchGroupsPage(next);
        if (!r || !r.data || !r.data.length) return false;
        this.groups = this.groups.concat(r.data);
        this.pageCount = next;
        if (r.pagination) this.total = Number(r.pagination.total);
        const wrap = document.querySelector('.os-table-wrap');
        const scrollTop = wrap ? wrap.scrollTop : 0;
        this.render();
        if (wrap) wrap.scrollTop = scrollTop;
        return this.groups.length < this.total;
    },

    async refresh(feedback, focusId) {
        if (!this.patientId) return;
        const keepPages = Math.max(1, this.pageCount || 1);
        try {
            // 并行重取已加载页（此前逐页串行 await + 每页整表重渲染：开一条医嘱要等 N 个来回）
            const results = await Promise.all(
                Array.from({ length: keepPages }, (_, i) => this.fetchGroupsPage(i + 1))
            );
            const first = results[0];
            if (!first) return;
            let groups = first.data || [];
            let pageCount = 1;
            let total = first.pagination ? Number(first.pagination.total) : groups.length;
            for (let p = 2; p <= keepPages; p++) {
                const rp = results[p - 1];
                if (!rp || !rp.data || !rp.data.length) break;
                groups = groups.concat(rp.data);
                pageCount = p;
                if (rp.pagination) total = Number(rp.pagination.total);
            }
            // 新开医嘱定位：列表按 id 升序、新医嘱在末尾，可能落在已加载页之外；
            // 继续顺序补齐后续页直到命中或触底（通常最多再补 1 页）
            if (focusId && !groups.some(g => Number(g.id) === Number(focusId)) && groups.length < total) {
                for (let p = pageCount + 1; groups.length < total; p++) {
                    const rp = await this.fetchGroupsPage(p);
                    if (!rp || !rp.data || !rp.data.length) break;
                    groups = groups.concat(rp.data);
                    pageCount = p;
                    if (rp.pagination) total = Number(rp.pagination.total);
                    if (groups.some(g => Number(g.id) === Number(focusId))) break;
                }
            }
            this.groups = groups;
            this.pageCount = pageCount;
            this.total = total;
            this.selected.clear();
            this._hoverRoot = null; // 重渲染后重置悬停组标记
            // 保留当前滚动位置 (仅新开医嘱才定位滚动, 其余操作不跳动)
            const wrap = document.querySelector('.os-table-wrap');
            const scrollTop = wrap ? wrap.scrollTop : 0;
            this.render();          // 全部页合并后一次渲染（原先每补一页渲染一次）
            if (wrap) wrap.scrollTop = scrollTop;
            if (focusId) this.focusOrder(focusId);
            if (this._scroll) ScrollLoad.check(this._scroll);
            if (feedback) Dom.toast('已刷新 ' + Format.shortTime(new Date()) + ' · 共 ' + this.groups.length + ' 组医嘱', 'success');
        } catch (e) { /* 已提示 */ }
    },



    /** 频次对应的默认执行时间 (自动生成, 可手动修改) */
    FREQ_DETAILS: { qd: '07', bid: '07-16', tid: '07-11-16', qid: '07-11-16-21', qn: '20' },

    autoFreqDetail(freq) {
        return this.FREQ_DETAILS[freq] || '';
    },

    /** 上一条医嘱的期间 (新开医嘱默认沿用, 开具后可点长/临切换) */
    lastOrderPeriod() {
        if (!this.groups || this.groups.length === 0) return 'long';
        const last = this.groups[this.groups.length - 1];
        return last.order_period === 'temp' ? 'temp' : 'long';
    },

    openMedication(event) { if (this.patientId) OrderQuickAdd.open('medication', event); },
    openNonmedication(event) { if (this.patientId) OrderQuickAdd.open('nonmedication', event); },

    /**
     * 成组: 草稿药品内容右侧「+」→ 调药品列表, 选择后追加为子医嘱 (组内排他)
     */
    addToGroup(rootId, event) {
        if (event) event.stopPropagation();
        const root = this.groups.find(g => g.id == rootId);
        if (!root || root.status !== 'draft' || root.category !== 'medication') return;
        OrderQuickAdd.openPick('medication', event, (item) => this.appendToGroup(rootId, item));
    },

    async appendToGroup(rootId, item) {
        // 前端排他预检 (后端同样拦截)
        const root = this.groups.find(g => g.id == rootId);
        if (root) {
            const members = [root].concat(root.sub_orders || []);
            const dup = members.some(m =>
                (item.drug_id && m.drug_id && m.drug_id == item.drug_id) || m.content === item.content
            );
            if (dup) { Dom.toast('该药品已在组内, 不能重复添加', 'error'); return; }
        }
        try {
            await AjaxLoader.api('orders/group-add', { method: 'POST', data: { root_id: rootId, item: item } });
            Dom.toast('已加入成组: ' + item.content, 'success');
            this.refresh();
        } catch (e) { /* 已提示 */ }
    },

    /**
     * 快捷删除 (内容右侧 x, 带确认防误操作):
     * - 主医嘱: 删除整组 (后端 batch-delete 级联子医嘱)
     * - 子医嘱: 只删除该子医嘱, 不影响主医嘱与其他子医嘱
     */
    async quickDelete(orderId, rootId, isRootRow, event) {
        if (event) event.stopPropagation();
        const ids = isRootRow ? [rootId] : [orderId];
        const tip = isRootRow ? '确认删除<b>整组</b>医嘱？（主医嘱及其全部子医嘱）' : '确认删除该条子医嘱？';
        Dom.confirm('删除确认', tip, async () => {
            try {
                await AjaxLoader.api('orders/batch-delete', { method: 'POST', data: { ids: ids } });
                Dom.toast(isRootRow ? '已删除整组医嘱' : '已删除子医嘱', 'success');
                OrderStation.refresh();
            } catch (e) { /* 已提示 */ }
        }, { danger: true, okText: '删除' });
    },
    /* 提交按钮: 默认提交该患者全部草稿医嘱 (无需勾选) */
    async submitAllDrafts() {
        await AjaxLoader.api('orders/submit', { method: 'POST', data: { patient_id: this.patientId } });
        Dom.toast('已提交全部草稿医嘱', 'success');
        this.refresh();
    },
    batchDelete() { OrderActions.batchDelete(); },
    stopAllLong() { OrderActions.stopAllLong(); },
    openSetPicker() { OrderActions.openSetPicker(); }
};

async function orders_order_station_init() {
    await App.mountShell(Auth.user.role);
    OrderStation.init();
}
</script>
<?php /* 渲染/编辑/辅助决策挂载到 OrderStation 单例 (拆分自本文件, 行为零变化) */ ?>
<?php include __DIR__ . '/os/assist.php'; ?>
<?php include __DIR__ . '/os/render.php'; ?>
<?php include __DIR__ . '/os/edit.php'; ?>
