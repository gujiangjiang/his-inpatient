// public/js/core/app.js
const App = {
    version: '0.19.3',
    roleRoutes: {
        admin: { module: 'admin', page: 'departments' },
        doctor: { module: 'patients', page: 'patient_list' },
        nurse: { module: 'nurse', page: 'station' },
        pharmacist: { module: 'pharmacy', page: 'inventory' },
        lab_tech: { module: 'lab', page: 'lab_exam' }
    },

    init() {
        this.loadCoreStyles();
    },

    loadCoreStyles() {
        // 动态加载核心样式
    },

    navigateTo(module, page) {
        AjaxLoader.loadPage(module, page);
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
