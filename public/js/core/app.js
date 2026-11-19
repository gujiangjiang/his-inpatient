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
