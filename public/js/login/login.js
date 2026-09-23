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

    // 工作站模式 (医生) 下, 患者上下文未锁定时停留在占位图
    if (window.WorkstationMode === true && !PatientContext.isActive()) {
        window.refreshWorkstationLayout();
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
    document.getElementById('login-container').style.display = 'block';
    document.getElementById('main-app').style.display = 'none';
}

function updateUserInfo() {
    const user = Auth.user;
    if (user) {
        document.getElementById('user-info').textContent = user.name + ' (' + Format.role(user.role) + ')';
    }
}

/**
 * 设置医院名称 (来自 sys_config)
 */
function setupHospitalName() {
    AjaxLoader.api('auth/setup-status', { silent: true }).then(r => {
        const el = document.getElementById('hospital-name');
        if (el && r.data && r.data.hospital_name) {
            el.textContent = r.data.hospital_name;
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

        // 医生工作站: 无患者上下文时进占位图, 不导航
        if (window.WorkstationMode === true && !PatientContext.isActive()) {
            document.getElementById('login-container').style.display = 'none';
            document.getElementById('main-app').style.display = 'flex';
            updateUserInfo();
            window.refreshWorkstationLayout();
            return;
        }

        if (!getHashRoute()) {
            window.location.hash = route.module + '/' + route.page;
        } else {
            handleHashChange();
        }
    } else {
        // 检查是否已初始化
        AjaxLoader.api('auth/setup-status', { silent: true }).then(result => {
            if (result.status === 'success' && !result.data.setup_completed) {
                document.getElementById('login-box').style.display = 'none';
                document.getElementById('setup-wizard').style.display = 'block';
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

// 初始化向导
document.getElementById('setup-btn').addEventListener('click', function() {
    const hospitalName = document.querySelector('[name="hospital_name"]').value;
    const hospitalCode = document.querySelector('[name="hospital_code"]').value;
    const username = document.querySelector('[name="username"]').value;
    const password = document.querySelector('[name="password"]').value;
    const password2 = document.querySelector('[name="password2"]').value;

    if (!hospitalName || !hospitalCode || !username || !password || !password2) {
        Dom.toast('请填写完整信息', 'error');
        return;
    }
    if (password !== password2) {
        Dom.toast('两次密码不一致', 'error');
        return;
    }

    AjaxLoader.api('auth/setup', {
        method: 'POST',
        data: { hospital_name: hospitalName, hospital_code: hospitalCode, username, password, password2 }
    }).then(result => {
        if (result.status === 'success') {
            Dom.toast('初始化完成', 'success');
            setTimeout(() => {
                Auth.login(username, password).then(() => {
                    window.location.hash = 'admin/departments';
                });
            }, 1000);
        }
    });
});