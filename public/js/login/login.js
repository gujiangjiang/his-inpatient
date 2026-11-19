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
    if (window.Notify) Notify.init(); /* 全局消息推送 (幂等; 覆盖所有进入主界面的路径) */

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

/** 应用医院品牌到登录页、左上角、标题栏和 PWA 元数据 */
function applyHospitalBranding(brand) {
    const names = HospitalBranding.setCurrent(brand || {});
    const name = names.hospital_name;
    const name2 = names.hospital_name2;
    const logo = brand && brand.logo_url;
    HospitalBranding.applyPair(document.getElementById('hospital-name'), document.getElementById('hospital-name2'));
    HospitalBranding.applyPair(document.getElementById('login-title'), document.getElementById('login-second-name'));
    if (logo) {
        ['hospital-logo', 'login-hospital-logo'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.src = logo;
        });
    }
    const icon = brand && brand.icon_192_url;
    if (icon) {
        const fav = document.getElementById('app-favicon');
        const touch = document.getElementById('app-touch-icon');
        if (fav) fav.href = icon;
        if (touch) touch.href = icon;
    }
    const manifest = document.getElementById('pwa-manifest');
    if (manifest && brand && brand.brand_revision) manifest.href = '/manifest?v=' + encodeURIComponent(brand.brand_revision);
    document.title = name + (name2 ? ' - ' + name2 : ' - 住院一体化系统');
}

/** 获取公开品牌信息并刷新界面 */
function setupHospitalName() {
    return AjaxLoader.api('auth/setup-status', { silent: true }).then(r => {
        if (r.data) applyHospitalBranding(r.data);
        return r.data;
    }).catch(() => null);
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
    if (window.Notify) Notify.init(); /* 全局消息推送 (SSE): 登录后启动一次 */
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
    const captcha = document.getElementById('login-captcha').value.trim();
    const captchaVisible = document.getElementById('captcha-group').style.display !== 'none';
    if (!username || !password) {
        Dom.toast('请输入用户名和密码', 'error');
        return;
    }
    if (captchaVisible && !captcha) {
        Dom.toast('请输入验证码', 'error');
        return;
    }
    Auth.login(username, password, captcha, captchaVisible ? '1' : '0').then(success => {
        if (success) {
            localStorage.setItem('his_login_fail_flag', '0');
            const role = Auth.getRole();
            initWorkstation(role);
            const route = App.roleRoutes[role] || App.roleRoutes.admin;
            window.location.hash = route.module + '/' + route.page;
        } else {
            localStorage.setItem('his_login_fail_flag', '1');
            showCaptcha(true);
            Dom.toast('登录失败', 'error');
        }
    });
});

/* ===== 验证码（参照门诊一体化）===== */
var _captchaMode = '';
function loadCaptchaMode() {
    // 从后端嗅探模式：直接请求 check-captcha?flag 探测
    AjaxLoader.api('auth/captcha?action=check&flag=0', { silent: true }).then(r => {
        if (r.data && r.data.require_captcha) { _captchaMode = 'force'; showCaptcha(); }
        else if (localStorage.getItem('his_login_fail_flag') === '1') { showCaptcha(); }
    }).catch(() => {});
}
var _captchaBlobUrl = '';
function loadCaptchaSvg() {
    const img = document.getElementById('login-captcha-img');
    if (!img) return;
    fetch('/api/auth/captcha?action=gen', {
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        cache: 'no-store',
        credentials: 'same-origin'
    }).then(function (res) {
        if (!res.ok) throw new Error('captcha http ' + res.status);
        return res.blob();
    }).then(function (blob) {
        if (_captchaBlobUrl) { try { URL.revokeObjectURL(_captchaBlobUrl); } catch (e) {} }
        _captchaBlobUrl = URL.createObjectURL(blob);
        img.src = _captchaBlobUrl;
    }).catch(function () { /* 拉取失败静默 */ });
}
function showCaptcha(force) {
    const g = document.getElementById('captcha-group');
    if (!g) return;
    g.style.display = '';
    loadCaptchaSvg();
}
function hideCaptcha() {
    const g = document.getElementById('captcha-group');
    if (g) g.style.display = 'none';
}
var _captchaTimer = null;
function probeCaptcha() {
    const username = document.getElementById('username') ? document.getElementById('username').value.trim() : '';
    const flag = localStorage.getItem('his_login_fail_flag') === '1' ? 1 : 0;
    AjaxLoader.api('auth/captcha?action=check&username=' + encodeURIComponent(username) + '&flag=' + flag, { silent: true }).then(r => {
        if (r.data && r.data.require_captcha) showCaptcha();
        else hideCaptcha();
    }).catch(() => {});
}
document.getElementById('login-captcha-box').addEventListener('click', loadCaptchaSvg);
if (document.getElementById('username')) {
    document.getElementById('username').addEventListener('blur', probeCaptcha);
    document.getElementById('username').addEventListener('input', function () {
        clearTimeout(_captchaTimer);
        _captchaTimer = setTimeout(probeCaptcha, 400);
    });
}
if (document.getElementById('password')) {
    document.getElementById('password').addEventListener('focus', probeCaptcha);
}
loadCaptchaMode();

// 初始化向导逻辑见 setup_wizard.js（多步：环境→数据库→初始化→站点→管理员→完成）
