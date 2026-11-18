// public/js/core/ajax.js
// 全局 AJAX 层：
//  - api()：统一 JSON 请求（自动带会话头、401 处理、错误 toast）
//  - loadPage()：SPA 页面片段加载
//  - 轻量加载指示：任何请求（含 silent）超过阈值未返回即在顶部显示进度条 +
//    右上转圈（引用计数，全部完成后隐藏）；非 silent 请求保留全屏遮罩，
//    遮罩文字带旋转动画。一处实现、全站生效，新增页面零接入成本。
const AjaxLoader = {
    silentCount: 0,
    loading: false,

    /* ==================== 轻量加载指示（顶部进度条 + 转圈） ==================== */
    _pending: 0,          // 在途请求引用计数
    _barTimer: null,

    _ensureLoadStyle() {
        if (document.getElementById('ajax-load-style')) return;
        const st = document.createElement('style');
        st.id = 'ajax-load-style';
        st.textContent =
            '#ajax-load-bar{position:fixed;top:0;left:0;right:0;height:3px;z-index:3000;' +
            'background:linear-gradient(90deg,#2563eb,#60a5fa,#2563eb);background-size:200% 100%;' +
            'animation:ajaxBarMove 1.2s linear infinite;pointer-events:none;box-shadow:0 1px 4px rgba(37,99,235,.35)}' +
            '#ajax-load-bar .ajax-spin{position:fixed;top:8px;right:14px;width:18px;height:18px;' +
            'border:2px solid rgba(37,99,235,.22);border-top-color:#2563eb;border-radius:50%;' +
            'animation:ajaxSpin .7s linear infinite;display:block}' +
            '@keyframes ajaxBarMove{0%{background-position:0% 0}100%{background-position:-200% 0}}' +
            '@keyframes ajaxSpin{to{transform:rotate(360deg)}}' +
            '#loading-overlay .ajax-spin{margin:0 auto 10px;width:34px;height:34px;' +
            'border:3px solid rgba(37,99,235,.2);border-top-color:#2563eb;border-radius:50%;' +
            'animation:ajaxSpin .8s linear infinite}';
        document.head.appendChild(st);
    },

    showBar() {
        this._pending++;
        if (this._pending !== 1) return;
        this._ensureLoadStyle();
        this._barTimer = setTimeout(() => {
            if (this._pending > 0 && !document.getElementById('ajax-load-bar')) {
                const bar = document.createElement('div');
                bar.id = 'ajax-load-bar';
                bar.innerHTML = '<span class="ajax-spin"></span>';
                document.body.appendChild(bar);
            }
        }, 300);   // 阈值：请求 300ms 未返回才出现，避免快速请求闪烁
    },

    hideBar() {
        if (this._pending > 0) this._pending--;
        if (this._pending === 0) {
            clearTimeout(this._barTimer);
            const bar = document.getElementById('ajax-load-bar');
            if (bar) bar.remove();
        }
    },

    /* ==================== 全屏遮罩（非 silent 请求/页面加载） ==================== */
    initLoading() {
        if (!this.loading) {
            this.loading = true;
            const overlay = document.createElement('div');
            overlay.id = 'loading-overlay';
            overlay.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(255,255,255,0.8);display:flex;flex-direction:column;align-items:center;justify-content:center;z-index:2000;';
            overlay.innerHTML = '<span class="ajax-spin"></span><div>加载中...</div>';
            document.body.appendChild(overlay);
            this._ensureLoadStyle();
        }
    },

    removeLoading() {
        const overlay = document.getElementById('loading-overlay');
        if (overlay) {
            document.body.removeChild(overlay);
            this.loading = false;
        }
    },

    api(path, options = {}) {
        const silent = options.silent || false;
        const method = (options.method || 'GET').toUpperCase();
        const data = options.data;
        const isForm = typeof FormData !== 'undefined' && data instanceof FormData;

        const opts = {
            method: method,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
            }
        };
        if (!isForm) opts.headers['Content-Type'] = 'application/json; charset=utf-8';
        // GET 请求禁用缓存, 避免读到陈旧数据
        if (method === 'GET') opts.cache = 'no-store';

        // 添加 session_id header (令牌回退)
        const sessionId = sessionStorage.getItem('session_id');
        if (sessionId) {
            opts.headers['X-Session-Id'] = sessionId;
        }

        if (data !== undefined) {
            opts.body = method === 'GET' ? undefined : (isForm ? data : JSON.stringify(data));
        }

        // 统一轻量加载指示：silent 与非 silent 都计入（快速请求 300ms 阈值防闪烁）
        this.showBar();
        if (!silent) {
            this.initLoading();
        }

        return fetch('/api/' + path, opts)
            .then(async (resp) => {
                this.hideBar();
                if (!silent) this.removeLoading();
                const result = await resp.json();
                // 按 HTTP 状态码判断 401
                if (resp.status === 401) {
                    Auth.handleUnauth();
                    throw new Error(result.message || '未授权');
                }
                if (resp.status !== 200) {
                    throw new Error(result.message || '请求失败');
                }
                return result;
            })
            .catch(err => {
                this.hideBar();
                if (!silent) this.removeLoading();
                // silent 模式不弹 toast (防登录页重复toast)
                if (!silent) {
                    Dom.toast(err.message, 'error');
                }
                throw err;
            });
    },

    loadPage(module, page) {
        const v = (typeof App !== 'undefined' && App.version) ? App.version : '';
        const url = '/js/' + module + '/' + page + '.php' + (v ? '?v=' + v : '');
        this.showBar();
        this.initLoading();
        return fetch(url, { cache: 'no-store' })
            .then(resp => {
                this.hideBar();
                if (!resp.ok) throw new Error('页面加载失败: ' + resp.status);
                return resp.text();
            })
            .then(html => {
                this.removeLoading();
                const app = document.getElementById('app-content') || document.getElementById('app');
                if (app) {
                    app.innerHTML = html;
                    // innerHTML 不会自动执行 <script>, 手动提取并按序执行
                    const scripts = app.querySelectorAll('script');
                    scripts.forEach(s => {
                        const ns = document.createElement('script');
                        ns.textContent = s.textContent;
                        s.parentNode.replaceChild(ns, s);
                    });
                }
                // 自定义下拉组件增强 (页面注入后)
                if (window.SelectPicker && app) SelectPicker.initInputs(app);
                // 执行页面初始化函数 (支持异步), 完成后标记侧边栏当前项
                const initFn = window[module + '_' + page + '_init'];
                const markActive = () => {
                    if (typeof App !== 'undefined' && App.markActiveMenu) App.markActiveMenu(module, page);
                };
                if (typeof initFn === 'function') {
                    Promise.resolve(initFn()).then(markActive).catch(markActive);
                } else {
                    markActive();
                }
            })
            .catch(err => {
                this.hideBar();
                this.removeLoading();
                Dom.toast('页面加载失败：' + err.message, 'error');
            });
    }
};