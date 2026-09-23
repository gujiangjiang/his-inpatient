// public/js/core/app.js
const App = {
    version: '0.1.0',
    roleRoutes: {
        admin: { module: 'admin', page: 'departments' },
        doctor: { module: 'doctor', page: 'patient_list' },
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

App.init();
