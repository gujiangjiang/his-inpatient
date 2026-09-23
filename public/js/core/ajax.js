// public/js/core/ajax.js
const AjaxLoader = {
    silentCount: 0,
    loading: false,

    initLoading() {
        if (!this.loading) {
            this.loading = true;
            const overlay = document.createElement('div');
            overlay.id = 'loading-overlay';
            overlay.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(255,255,255,0.8);display:flex;align-items:center;justify-content:center;z-index:2000;';
            overlay.innerHTML = '<div>加载中...</div>';
            document.body.appendChild(overlay);
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

        const opts = {
            method: method,
            headers: {
                'Content-Type': 'application/json; charset=utf-8',
            }
        };

        // 添加 session_id header (令牌回退)
        const sessionId = sessionStorage.getItem('session_id');
        if (sessionId) {
            opts.headers['X-Session-Id'] = sessionId;
        }

        if (data !== undefined) {
            opts.body = method === 'GET' ? undefined : JSON.stringify(data);
        }

        if (!silent) {
            this.initLoading();
        }

        return fetch('/api/' + path, opts)
            .then(async (resp) => {
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
                if (!silent) this.removeLoading();
                // silent 模式不弹 toast (防登录页重复toast)
                if (!silent) {
                    Dom.toast(err.message, 'error');
                }
                throw err;
            });
    },

    loadPage(module, page) {
        const url = '/js/' + module + '/' + page + '.html';
        this.initLoading();
        return fetch(url)
            .then(resp => {
                if (!resp.ok) throw new Error('页面加载失败: ' + resp.status);
                return resp.text();
            })
            .then(html => {
                this.removeLoading();
                const app = document.getElementById('app-content') || document.getElementById('app');
                if (app) {
                    app.innerHTML = html;
                }
                // 执行页面初始化函数
                const initFn = window[module + '_' + page + '_init'];
                if (typeof initFn === 'function') {
                    initFn();
                }
            })
            .catch(err => {
                this.removeLoading();
                Dom.toast('页面加载失败：' + err.message, 'error');
            });
    }
};
