// public/js/login/login.js

function getHashRoute() {
    const hash = window.location.hash.replace('#', '');
    if (!hash) return null;
    const parts = hash.split('/');
    if (parts.length >= 2) {
        return { module: parts[0], page: parts[1] };
    }
    return null;
}

async function handleHashChange() {
    const route = getHashRoute();
    if (!route) return;

    const user = Auth.user;
    if (!user) {
        showLogin();
        return;
    }

    // 认证通过，显示主界面
    document.getElementById('login-container').style.display = 'none';
    document.getElementById('main-app').style.display = 'flex';
    updateUserInfo();

    const key = route.module + '/' + route.page;
    const browsePages = ['patients/patient_list', 'patients/beds'];

    // 工作站模式 (医生): 浏览页无需患者上下文; 其他页未选患者时跳回患者卡片墙
    if (window.WorkstationMode === true && !PatientContext.isActive() && !browsePages.includes(key)) {
        Dom.toast('请先在患者卡片墙选择患者', 'info');
        window.location.hash = 'patients/patient_list';
        return;
    }

    // 加载 sidebar
    const sidebar = await fetch('/js/shared/sidebar_' + user.role + '.php').then(r => r.text());
    document.getElementById('sidebar').innerHTML = sidebar;
    window.refreshWorkstationLayout();

    // 加载页面
    await AjaxLoader.loadPage(route.module, route.page);
}

function showLogin() {
    document.getElementById('login-container').style.display = 'flex';
    document.getElementById('main-app').style.display = 'none';
}

function updateUserInfo() {
    const user = Auth.user;
    if (user) {
        document.getElementById('user-info').textContent = user.name + ' (' + Format.role(user.role) + ')';
    }
}

/**
 * 设置医院名称 (来自 sys_config) - 同时更新登录页标题
 */
function setupHospitalName() {
    AjaxLoader.api('auth/setup-status', { silent: true }).then(r => {
        const name = r.data && r.data.hospital_name ? r.data.hospital_name : '';
        const el = document.getElementById('hospital-name');
        if (el && name) {
            el.textContent = name;
        }
        const loginTitle = document.getElementById('login-title');
        if (loginTitle && name) {
            loginTitle.textContent = name;
        }
    }).catch(() => {});
}

/**
 * 登录后初始化工作站模式
 */
function initWorkstation(role) {
    const switcher = document.getElementById('patient-switcher');
    if (role === 'doctor') {
        window.WorkstationMode = true;
        if (switcher) switcher.style.display = 'flex';
        PatientSwitcher.init();
        PatientSwitcher.updateTrigger();
    } else {
        window.WorkstationMode = false;
        if (switcher) switcher.style.display = 'none';
    }
}

window.addEventListener('hashchange', handleHashChange);

// 初始化
Auth.check({ silent: true }).then(loggedIn => {
    setupHospitalName();
    if (loggedIn) {
        const role = Auth.getRole();
        initWorkstation(role);
        const route = App.roleRoutes[role] || App.roleRoutes.admin;

        // 医生工作站: 无患者上下文时进入患者卡片墙 (患者主页面)
        if (window.WorkstationMode === true && !PatientContext.isActive()) {
            document.getElementById('login-container').style.display = 'none';
            document.getElementById('main-app').style.display = 'flex';
            updateUserInfo();
            if (window.location.hash !== '#patients/patient_list') {
                window.location.hash = 'patients/patient_list';
            } else {
                handleHashChange();
            }
            return;
        }

        if (!getHashRoute()) {
            window.location.hash = route.module + '/' + route.page;
        } else {
            handleHashChange();
        }
    } else {
        // 检查是否已初始化（未初始化 → 多步初始化向导）
        AjaxLoader.api('auth/setup-status', { silent: true }).then(result => {
            if (result.status === 'success' && !result.data.setup_completed) {
                document.getElementById('login-box').style.display = 'none';
                document.getElementById('setup-wizard').style.display = 'block';
                SetupWizard.init();
            } else {
                showLogin();
            }
        }).catch(() => {
            showLogin();
        });
    }
});

// 登录表单提交
document.getElementById('login-form').addEventListener('submit', function(e) {
    e.preventDefault();
    const username = this.username.value;
    const password = this.password.value;
    if (!username || !password) {
        Dom.toast('请输入用户名和密码', 'error');
        return;
    }
    Auth.login(username, password).then(success => {
        if (success) {
            const role = Auth.getRole();
            initWorkstation(role);
            const route = App.roleRoutes[role] || App.roleRoutes.admin;
            window.location.hash = route.module + '/' + route.page;
        } else {
            Dom.toast('登录失败', 'error');
        }
    });
});

// 初始化向导逻辑见 setup_wizard.js（多步：环境→数据库→初始化→站点→管理员→完成）