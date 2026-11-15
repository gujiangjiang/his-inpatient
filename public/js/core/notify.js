// public/js/core/notify.js - 全局消息推送 (SSE 长连接, 前端零轮询)
// 顶栏铃铛 + 下拉面板: 未读角标/消息列表/全部已读; 新消息届到 toast 提醒;
// 业务处理完成 (如医嘱核对/审核通过) 服务端删除通知 → 下次推送自动消失。
const Notify = {
    started: false, unread: 0, items: [], es: null, lastSeenId: 0, panelOpen: false,

    init() {
        /* 注意: Auth 为 const 词法全局 (非 window 属性), 必须用 typeof 判断 */
        if (this.started || typeof Auth === 'undefined') return;
        this.started = true;
        this.mount();
        this.fetchList();
        this.connect();
    },

    mount() {
        const bell = document.getElementById('notify-bell');
        const panel = document.getElementById('notify-panel');
        if (!bell || !panel) return;
        bell.addEventListener('click', (e) => {
            e.stopPropagation();
            this.panelOpen = !this.panelOpen;
            panel.style.display = this.panelOpen ? '' : 'none';
            if (this.panelOpen) this.fetchList();
        });
        panel.addEventListener('click', (e) => e.stopPropagation());
        document.addEventListener('click', () => {
            if (this.panelOpen) { this.panelOpen = false; panel.style.display = 'none'; }
        });
        const readall = document.getElementById('notify-readall');
        if (readall) readall.addEventListener('click', () => this.markAll());
    },

    async fetchList() {
        try {
            const r = await AjaxLoader.api('notify/list', { silent: true });
            this.apply(r.data);
        } catch (e) { /* 静默 */ }
    },

    /** 状态应用: 角标/面板渲染 + 新消息 toast (SSE 推送与主动拉取共用) */
    apply(data) {
        if (!data) return;
        const prevMax = this.lastSeenId;
        this.unread = data.unread || 0;
        this.items = data.items || [];
        if (this.items.length) {
            const maxId = this.items[0].id;
            const fresh = this.items.filter((x) => x.id > prevMax && !x.is_read);
            if (prevMax && fresh.length) {
                fresh.slice(0, 3).forEach((x) => Dom.toast(x.title, 'info'));
            }
            this.lastSeenId = Math.max(prevMax, maxId);
        }
        this.render();
    },

    render() {
        const badge = document.getElementById('notify-badge');
        if (badge) {
            badge.textContent = this.unread > 99 ? '99+' : String(this.unread);
            badge.style.display = this.unread > 0 ? '' : 'none';
        }
        const list = document.getElementById('notify-list');
        if (!list) return;
        if (!this.items.length) {
            list.innerHTML = '<div class="notify-empty">暂无消息</div>';
            return;
        }
        list.innerHTML = this.items.map((x) => {
            return '<div class="notify-item' + (x.is_read ? '' : ' unread') + '" data-id="' + x.id + '">'
                + '<div class="notify-item-title">' + Format.escape(x.title) + '</div>'
                + (x.body ? '<div class="notify-item-body">' + Format.escape(x.body) + '</div>' : '')
                + '<div class="notify-item-time">' + Format.escape(String(x.created_at || '').slice(0, 16)) + '</div>'
                + '</div>';
        }).join('');
        list.querySelectorAll('.notify-item').forEach((el) => {
            el.addEventListener('click', () => {
                const item = this.items.find((x) => String(x.id) === String(el.dataset.id));
                if (!item) return;
                this.markOne(item.id);
                this.closePanel();
                const link = String(item.link || '').replace(/^#/, '');
                if (link) {
                    const parts = link.split('/');
                    if (parts.length >= 2 && typeof navigateTo === 'function') navigateTo(parts[0], parts[1]);
                }
            });
        });
    },

    closePanel() {
        this.panelOpen = false;
        const panel = document.getElementById('notify-panel');
        if (panel) panel.style.display = 'none';
    },

    /** SSE 长连接: 服务端状态指纹变化即推送; 断开由 EventSource 自动重连 (retry 3s) */
    connect() {
        if (!window.EventSource) return;
        try { this.es = new EventSource('/api/notify/stream'); } catch (e) { return; }
        this.es.addEventListener('notify', (ev) => {
            try { this.apply(JSON.parse(ev.data)); } catch (e) { /* ignore */ }
        });
    },

    async markOne(id) {
        try {
            await AjaxLoader.api('notify/read', { method: 'POST', data: { ids: [id] }, silent: true });
            const item = this.items.find((x) => String(x.id) === String(id));
            if (item) item.is_read = 1;
            this.unread = Math.max(0, this.unread - 1);
            this.render();
        } catch (e) { /* ignore */ }
    },

    async markAll() {
        try {
            await AjaxLoader.api('notify/read', { method: 'POST', data: { all: 1 }, silent: true });
            this.items.forEach((x) => { x.is_read = 1; });
            this.unread = 0;
            this.render();
        } catch (e) { /* ignore */ }
    }
};

window.Notify = Notify;
