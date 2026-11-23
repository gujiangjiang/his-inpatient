// public/js/core/app.js
const App = {
    version: window.HIS_APP_VERSION || '1',
    roleRoutes: {
        admin: { module: 'admin', page: 'departments' },
        doctor: { module: 'patients', page: 'patient_list' },
        nurse: { module: 'nurse', page: 'station' },
        pharmacist: { module: 'pharmacy', page: 'inventory' },
        lab_tech: { module: 'lab', page: 'lab_exam' }
    },

    init() {
        this.loadCoreStyles();
        // 移动端响应式初始化：侧边栏默认隐藏/抽屉、点菜单收起
        if (window.matchMedia && !window.matchMedia('(max-width: 768px)').matches) {
            const sidebar = document.getElementById('sidebar');
            if (sidebar && localStorage.getItem('his_sidebar_mini') === '1') {
                sidebar.classList.add('sidebar-mini');
            }
        }
        this.initMobile();
    },

    loadCoreStyles() {
        // 动态加载核心样式
    },

    navigateTo(module, page) {
        AjaxLoader.loadPage(module, page);
    },

    // 侧边栏折叠/抽屉（移动端隐藏 + 点菜单收起）
    toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        const mask = document.getElementById('sidebar-mask') || this.ensureMask();
        const isMobile = window.matchMedia('(max-width: 768px)').matches;
        if (isMobile) {
            sidebar.classList.toggle('open');
            if (mask) mask.classList.toggle('show', sidebar.classList.contains('open'));
        } else {
            sidebar.classList.toggle('sidebar-mini');
            localStorage.setItem('his_sidebar_mini', sidebar.classList.contains('sidebar-mini') ? '1' : '0');
        }
    },

    ensureMask() {
        const mask = document.createElement('div');
        mask.id = 'sidebar-mask';
        mask.className = 'sidebar-mask';
        mask.addEventListener('click', () => {
            document.getElementById('sidebar').classList.remove('open');
            mask.classList.remove('show');
        });
        document.body.appendChild(mask);
        return mask;
    },

    initMobile() {
        const sidebar = document.getElementById('sidebar');
        // 移动端：菜单点击后收起抽屉
        if (sidebar) {
            sidebar.addEventListener('click', function (e) {
                if (window.matchMedia('(max-width: 768px)').matches && e.target.closest('.menu-item')) {
                    sidebar.classList.remove('open');
                    const mask = document.getElementById('sidebar-mask');
                    if (mask) mask.classList.remove('show');
                }
            });
        }
        // 病历画布：窄屏整体等比缩放（不重排版）——缩放只作用于纸张本身
        if (window.matchMedia && window.matchMedia('(max-width: 900px)').matches) {
            this.bindEmrPageZoom();
        }
        // 跨断点切换 (桌面↔窄屏) 时补绑 / 还原
        const self = this;
        window.addEventListener('resize', function () {
            if (window.matchMedia && window.matchMedia('(max-width: 900px)').matches) {
                if (!self._emrZoomBound) self.bindEmrPageZoom();
                return;
            }
            // 回到桌面: 清除窄屏缩放内联样式, 恢复原生桌面表现
            const wrap = document.querySelector('.emr-editor-scroll');
            const page = wrap && wrap.querySelector('.emr-editor-page');
            if (wrap) { wrap.style.display = ''; wrap.style.justifyContent = ''; }
            if (page) { page.style.transform = ''; page.style.margin = ''; }
        });
    },

    /**
     * 病历 A4 画布窄屏缩放: 纸张按可视宽度等比缩小 (元素布局不变),
     * 容器同步留白以容纳缩放后的纸张, 避免横向滚动条
     */
    bindEmrPageZoom() {
        if (this._emrZoomBound) return;
        this._emrZoomBound = true;
        const apply = () => {
            const wrap = document.querySelector('.emr-editor-scroll');
            const page = wrap && wrap.querySelector('.emr-editor-page');
            if (!wrap || !page) return;
            /* 容器不可见 (编辑器未打开/隐藏) 时 clientWidth 为 0:
               此时计算会得到 0 甚至负缩放并残留, 文书打开后内容"消失"。
               跳过本次, 由 emr_fitEditorZoom 在编辑器可见后再适配。 */
            if (wrap.clientWidth <= 24) return;
            const avail = wrap.clientWidth - 20;
            const scale = Math.min(1, avail / 794);
            page.style.transform = 'scale(' + scale + ')';
            page.style.transformOrigin = 'top center';
            page.style.margin = '0 auto';
            wrap.style.display = 'flex';
            wrap.style.justifyContent = 'center';
        };
        // 纸张在页面片段注入后才存在: 观察内容区子节点增减 (不逐字符重算)
        let timer = null;
        const schedule = () => {
            clearTimeout(timer);
            timer = setTimeout(apply, 80);
        };
        const target = document.getElementById('app-content') || document.body;
        if (target) {
            const obs = new MutationObserver((muts) => {
                // 仅当新增/移除的是整块页面或画布时才重算
                const hit = muts.some(m => m.type === 'childList'
                    && (m.target === target
                        || (m.target.classList && m.target.classList.contains('emr-editor-scroll'))));
                if (hit) schedule();
            });
            obs.observe(target, { childList: true, subtree: true });
        }
        window.addEventListener('resize', schedule);
    },

    /** 窄屏病历画布缩放 (供 SPA 页面加载后调用, 避免重复绑定) */
    emrPageZoomNow() {
        const wrap = document.querySelector('.emr-editor-scroll');
        const page = wrap && wrap.querySelector('.emr-editor-page');
        if (!wrap || !page) return;
        /* 容器不可见 (clientWidth 0) 时跳过, 防止负缩放残留 (见 bindEmrPageZoom 注释) */
        if (wrap.clientWidth <= 24) return;
        const avail = wrap.clientWidth - 20;
        const scale = Math.min(1, avail / 794);
        page.style.transform = 'scale(' + scale + ')';
        page.style.transformOrigin = 'top center';
        page.style.margin = '0 auto';
        wrap.style.display = 'flex';
        wrap.style.justifyContent = 'center';
    },

    // 侧边栏当前页高亮 (页面加载完成后调用)
    markActiveMenu(module, page) {
        const needle = "'" + module + "','" + page + "'";
        document.querySelectorAll('#sidebar .menu-item').forEach(function(item) {
            const oc = item.getAttribute('onclick') || '';
            item.classList.toggle('active', oc.indexOf(needle) !== -1);
        });
        // 占位页: 多个菜单共用同一路由, 仅高亮标题匹配的菜单项
        if (module === 'doctor' && page === 'placeholder' && window.__placeholderTitle) {
            document.querySelectorAll('#sidebar .menu-item[data-placeholder]').forEach(function(item) {
                item.classList.toggle('active', item.getAttribute('data-placeholder') === window.__placeholderTitle);
            });
        }
    },

    /* ==================== 页面 init 公共样板 (原 38 处复制收敛) ==================== */

    /** 侧边栏内容缓存 (按角色; 登录会话内有效, 内容为静态 PHP 片段) */
    _sidebarCache: {},

    /**
     * 加载角色侧边栏并注入 #sidebar (带 r.ok 检查与失败占位, 替代散落的裸 fetch)
     * @returns {Promise<boolean>} 是否成功
     */
    async loadSidebar(role) {
        const sidebar = document.getElementById('sidebar');
        if (!sidebar || !role) return false;
        const html = this._sidebarCache[role] !== undefined
            ? this._sidebarCache[role]
            : await fetch('/js/shared/sidebar_' + role + '.php').then(r => {
                if (!r.ok) throw new Error('sidebar http ' + r.status);
                return r.text();
            }).catch(() => null);
        if (html === null) {
            sidebar.innerHTML = '<div class="menu-item">侧边栏加载失败，请刷新重试</div>';
            return false;
        }
        this._sidebarCache[role] = html;
        sidebar.innerHTML = html;
        return true;
    },

    /**
     * 挂载工作台外壳 (页面 *_init 的公共第一步):
     * 显示主界面、隐藏登录层、按需加载侧边栏
     */
    async mountShell(role) {
        const mainApp = document.getElementById('main-app');
        const loginBox = document.getElementById('login-container');
        if (mainApp) mainApp.style.display = 'flex';
        if (loginBox) loginBox.style.display = 'none';
        if (role) await this.loadSidebar(role);
    }
};

// 全局 navigateTo: 供 sidebar onclick 使用, 始终可用; 同步 hash 路由 (#module/page)
window.navigateTo = function(module, page) {
    const target = '#' + module + '/' + page;
    if (window.location.hash === target) {
        AjaxLoader.loadPage(module, page);
    } else {
        window.location.hash = target;
    }
};

// 未处理 Promise rejection 全局兜底: 错误 toast 已由 AjaxLoader 统一负责,
// 此处阻断控制台噪音并留调试线索 (弹窗内 async onclick 失败时弹窗关闭语义不受影响)
window.addEventListener('unhandledrejection', function (e) {
    e.preventDefault();
    if (window.console && console.warn) console.warn('[unhandledrejection]', e.reason);
});

// 打开占位页 (多个占位菜单共用 doctor/placeholder 路由时, 用标题区分当前项)
window.openPlaceholder = function(title) {
    window.__placeholderTitle = title;
    window.navigateTo('doctor', 'placeholder');
};

App.init();
