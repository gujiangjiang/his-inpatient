// public/js/core/icd_picker.js - 通用 ICD-10 诊断选择器 (浮层搜索)
// 用法: IcdPicker.bind(inputEl, { codeEl: 隐藏编码输入框 }) — 输入自动搜索, 浮现全站统一样式的结果浮层
// 特性: 固定定位浮层 (不被模态框裁剪, 层级正确) / 分页滚动加载 / ↑↓ 键盘导航 / 回车选中 / 点击外部或 Esc 关闭
const IcdPicker = {
    _panel: null,
    _state: null,

    bind(input, opts) {
        if (!input || input._icdBound) return;
        input._icdBound = true;
        opts = opts || {};
        const self = this;
        let timer = null;
        input.addEventListener('input', function() {
            clearTimeout(timer);
            const kw = input.value.trim();
            if (!kw) { self.close(); return; }
            timer = setTimeout(function() { self.search(input, opts, kw, true); }, 250);
        });
        input.addEventListener('focus', function() {
            if (input.value.trim()) self.search(input, opts, input.value.trim(), true);
        });
        input.addEventListener('keydown', function(e) {
            const st = self._state;
            if (!st || st.input !== input || !self._panel) return;
            if (e.key === 'ArrowDown') { e.preventDefault(); self.move(1); }
            else if (e.key === 'ArrowUp') { e.preventDefault(); self.move(-1); }
            else if (e.key === 'Enter') {
                if (st.cursor >= 0 && st.items[st.cursor]) { e.preventDefault(); self.pick(st.items[st.cursor]); }
                else if (st.items.length === 1) { e.preventDefault(); self.pick(st.items[0]); }
            } else if (e.key === 'Escape') { self.close(); }
        });
    },

    search(input, opts, kw, reset) {
        const self = this;
        let st = this._state;
        if (!st || st.input !== input) {
            this.close();
            st = this._state = {
                input: input, opts: opts, kw: '', offset: 0, total: 0,
                items: [], cursor: -1, loading: false, hasMore: true, scroll: null
            };
            this._panel = document.createElement('div');
            this._panel.className = 'icd-pop';
            this._panel.innerHTML = '<div class="icd-pop-res"></div>';
            document.body.appendChild(this._panel);
            // 防止点击浮层时输入框失焦
            this._panel.addEventListener('mousedown', function(e) { e.preventDefault(); });
            st.scroll = ScrollLoad.attach(this._panel.querySelector('.icd-pop-res'), function() {
                return self.loadMore();
            }, { autoCheck: true });
            this.position(input);
        }
        if (reset) {
            st.kw = kw;
            st.offset = 0;
            st.total = 0;
            st.items = [];
            st.cursor = -1;
            st.hasMore = true;
            st.loading = false;
            ScrollLoad.reset(st.scroll);
            this._panel.querySelector('.icd-pop-res').innerHTML = '';
        }
        return this.loadMore();
    },

    loadMore() {
        const st = this._state;
        if (!st || st.loading || !st.kw) return Promise.resolve(false);
        if (!st.hasMore && st.offset > 0) return Promise.resolve(false);
        st.loading = true;
        const self = this;
        return AjaxLoader.api('emr/icd-search?keyword=' + encodeURIComponent(st.kw) + '&limit=20&offset=' + st.offset, { silent: true })
            .then(function(r) {
                st.loading = false;
                const rows = r.data || [];
                const res = self._panel ? self._panel.querySelector('.icd-pop-res') : null;
                if (!res) return false;
                if (st.offset === 0 && !rows.length) {
                    res.innerHTML = '<div class="diag-pop-item diag-pop-none">无匹配诊断</div>';
                    st.hasMore = false;
                    return false;
                }
                res.insertAdjacentHTML('beforeend', rows.map(function(x) {
                    return '<div class="diag-pop-item" data-code="' + Format.escape(x.code) + '" data-name="' + Format.escape(x.name) + '">' +
                        '<span class="dp-code">' + Format.escape(x.code) + '</span>' +
                        '<span class="dp-name">' + Format.escape(x.name) + '</span></div>';
                }).join(''));
                res.querySelectorAll('.diag-pop-item[data-code]').forEach(function(el) {
                    if (el._bound) return;
                    el._bound = true;
                    el.addEventListener('click', function() {
                        self.pick({ code: el.dataset.code, name: el.dataset.name });
                    });
                });
                st.items = st.items.concat(rows);
                st.offset += rows.length;
                const total = r.total != null ? Number(r.total) : 0;
                st.hasMore = rows.length >= 20 && (total === 0 || st.offset < total);
                if (st.scroll) ScrollLoad.check(st.scroll);
                return st.hasMore;
            }).catch(function() { st.loading = false; return false; });
    },

    move(delta) {
        const st = this._state;
        if (!st || !this._panel) return;
        const els = this._panel.querySelectorAll('.diag-pop-item[data-code]');
        if (!els.length) return;
        const base = st.cursor < 0 ? (delta > 0 ? -1 : 0) : st.cursor;
        st.cursor = Math.max(0, Math.min(els.length - 1, base + delta));
        els.forEach(function(el, i) { el.classList.toggle('cursor', i === st.cursor); });
        if (els[st.cursor]) els[st.cursor].scrollIntoView({ block: 'nearest' });
    },

    pick(item) {
        const st = this._state;
        if (!st || !item) return;
        st.input.value = item.name;
        const codeEl = st.opts.codeEl;
        if (codeEl) codeEl.value = item.code || '';
        if (typeof st.opts.onPick === 'function') st.opts.onPick(item);
        this.close();
    },

    position(input) {
        if (!this._panel) return;
        const rect = input.getBoundingClientRect();
        const w = Math.max(rect.width, 300);
        this._panel.style.width = w + 'px';
        this._panel.style.left = Math.max(8, Math.min(rect.left, window.innerWidth - w - 8)) + 'px';
        const h = 240;
        const below = rect.bottom + 4;
        this._panel.style.top = (below + h > window.innerHeight - 8 && rect.top - h - 4 > 8)
            ? (rect.top - h - 4) + 'px' : below + 'px';
    },

    close() {
        if (this._panel && this._panel.parentNode) this._panel.parentNode.removeChild(this._panel);
        this._panel = null;
        this._state = null;
    }
};

window.IcdPicker = IcdPicker;
