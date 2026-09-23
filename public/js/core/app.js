// public/js/core/app.js
const App = {
    version: '0.11.3',
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

// 全局 navigateTo: 供 sidebar onclick 使用, 始终可用
window.navigateTo = function(module, page) {
    AjaxLoader.loadPage(module, page);
};

App.init();
