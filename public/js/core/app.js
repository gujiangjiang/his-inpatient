// public/js/core/app.js
const App = {
    version: '0.13.30',
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

App.init();
