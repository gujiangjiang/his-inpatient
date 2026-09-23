// public/js/login/login.js
function login_init() {
    const form = document.getElementById('login-form');
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            const username = this.username.value;
            const password = this.password.value;
            if (!username || !password) {
                Dom.toast('请输入用户名和密码', 'error');
                return;
            }
            Auth.login(username, password).then(success => {
                if (success) {
                    // 跳转到工作站
                    const role = Auth.getRole();
                    const route = App.roleRoutes[role] || App.roleRoutes.admin;
                    window.location.hash = route.module + '/' + route.page;
                } else {
                    Dom.toast('登录失败', 'error');
                }
            });
        });
    }

    const setupBtn = document.getElementById('setup-btn');
    if (setupBtn) {
        setupBtn.addEventListener('click', function() {
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
                    // 登录
                    Auth.login(username, password).then(() => {
                        window.location.hash = 'admin/departments';
                    });
                }
            });
        });
    }
}

// 初始化向导检测
Auth.check({ silent: true }).then(loggedIn => {
    if (loggedIn) {
        const role = Auth.getRole();
        const route = App.roleRoutes[role] || App.roleRoutes.admin;
        window.location.hash = route.module + '/' + route.page;
    } else {
        // 检查是否已初始化
        AjaxLoader.api('auth/setup-status', { silent: true }).then(result => {
            if (result.status === 'success' && !result.data.setup_completed) {
                // 显示初始化向导
                document.getElementById('login-box').style.display = 'none';
                document.getElementById('setup-wizard').style.display = 'block';
            }
        });
    }
});
