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

// 打开占位页 (多个占位菜单共用 doctor/placeholder 路由时, 用标题区分当前项)
window.openPlaceholder = function(title) {
    window.__placeholderTitle = title;
    window.navigateTo('doctor', 'placeholder');
};

App.init();
