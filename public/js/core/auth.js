// public/js/core/auth.js
const Auth = {
    user: null,
    initPromise: null,

    init() {
        if (this.initPromise) return this.initPromise;
        this.initPromise = this.check();
        return this.initPromise;
    },

    async check(opts = {}) {
        const silent = opts.silent === true;
        try {
            const result = await AjaxLoader.api('auth/check', { silent: true });
            if (result.status === 'success' && result.data) {
                this.user = result.data;
                return true;
            }
            this.user = null;
            return false;
        } catch (e) {
            this.user = null;
            if (!silent) {
                Dom.toast('未授权', 'error');
            }
            return false;
        }
    },

    async login(username, password, captcha = '', needCaptcha = '0') {
        try {
            const result = await AjaxLoader.api('auth/login', { method: 'POST', data: { username, password, captcha, need_captcha: needCaptcha } });
            if (result.status === 'success') {
                this.user = result.data.user;
                sessionStorage.setItem('session_id', result.data.session_id);
                return true;
            }
            return false;
        } catch (e) {
            return false;
        }
    },

    logout() {
        return AjaxLoader.api('auth/logout', { method: 'POST', silent: true }).then(() => {
            sessionStorage.removeItem('session_id');
            this.user = null;
            window.location.reload();
        });
    },

    handleUnauth() {
        sessionStorage.removeItem('session_id');
        this.user = null;
        /* 会话失效: 关闭 SSE 长连接 (401 后 EventSource 不再重连, 但持连接应主动释放) */
        if (window.Notify && typeof Notify.stop === 'function') Notify.stop();
        // 跳转到登录页
        if (window.location.hash && window.location.hash !== '#login') {
            window.location.hash = 'login';
        }
    },

    getRole() {
        return this.user ? this.user.role : null;
    },

    isLoggedIn() {
        return this.user !== null;
    }
};
