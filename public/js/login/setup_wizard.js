// public/js/login/setup_wizard.js - 多步初始化向导
// 流程：环境检测 → 数据库配置 → 数据库初始化 → 站点信息（含时区）→ 管理员 → 完成
// 阶段由后端 config.db setup.stage 断点续填；完成后自动登录进入系统。
var SetupWizard = (function () {
    var state = { status: null, adminUser: '', adminPass: '' };
    var COMMON_TZ = ['Asia/Shanghai', 'Asia/Urumqi', 'Asia/Hong_Kong', 'Asia/Taipei',
        'Asia/Tokyo', 'Asia/Singapore', 'America/New_York', 'Europe/London', 'UTC'];

    function el(id) { return document.getElementById(id); }

    function api(path, data) {
        return AjaxLoader.api(path, { method: 'POST', data: data || {} });
    }

    /** 显示指定步骤面板并更新步骤条状态 */
    function show(step) {
        var panes = document.querySelectorAll('#setup-wizard .wizard-pane');
        for (var i = 0; i < panes.length; i++) {
            panes[i].style.display = (panes[i].getAttribute('data-pane') === step) ? 'block' : 'none';
        }
        var chips = document.querySelectorAll('#wizard-steps .wizard-step');
        var reached = false;
        for (var j = 0; j < chips.length; j++) {
            var s = chips[j].getAttribute('data-step');
            chips[j].className = 'wizard-step';
            if (s === step) { chips[j].className += ' active'; reached = true; }
            else if (!reached) { chips[j].className += ' done'; }
        }
    }

    /** 环境检测列表渲染（不用图形字符，仅状态点 + 文案） */
    function renderEnv(env) {
        var items = [
            { ok: env.php.ok, text: 'PHP 版本：' + env.php.version + '（需要 ' + env.php.required + '）' },
            { ok: env.pdo.ok, text: 'PDO 数据库驱动：' + (env.pdo.drivers.length ? env.pdo.drivers.join(' / ') : '未检测到') },
            { ok: env.data.ok, text: '数据目录可写：' + env.data.path }
        ];
        var html = '';
        var pass = true;
        for (var i = 0; i < items.length; i++) {
            html += '<li><i class="wz-dot ' + (items[i].ok ? 'ok' : 'bad') + '"></i><span>' + Format.escape(items[i].text) + '</span></li>';
            if (!items[i].ok) pass = false;
        }
        el('wz-env').innerHTML = html;
        el('wz-env-next').disabled = !pass;
    }

    /** 时区下拉：常用置顶 + 全量列表 */
    function renderTimezones(list, selected) {
        var sel = el('wz-site-tz');
        var html = '<optgroup label="常用">';
        for (var i = 0; i < COMMON_TZ.length; i++) {
            html += '<option value="' + COMMON_TZ[i] + '">' + COMMON_TZ[i] + '</option>';
        }
        html += '</optgroup><optgroup label="全部时区">';
        for (var j = 0; j < list.length; j++) {
            html += '<option value="' + list[j] + '">' + list[j] + '</option>';
        }
        html += '</optgroup>';
        sel.innerHTML = html;
        if (selected) sel.value = selected;
        if (!sel.value) sel.value = 'Asia/Shanghai';
    }

    /** 数据库类型切换：SQLite 路径 / 服务器参数 */
    function bindDbType() {
        el('wz-db-type').addEventListener('change', function () {
            var isSqlite = this.value === 'sqlite';
            document.querySelector('.wz-db-sqlite').style.display = isSqlite ? 'block' : 'none';
            document.querySelector('.wz-db-sql').style.display = isSqlite ? 'none' : 'block';
        });
    }

    /** 初始化（由 login.js 在未初始化时调用） */
    function init() {
        api('setup/status').then(function (r) {
            var d = r.data;
            state.status = d;
            renderEnv(d.env);
            renderTimezones(d.timezones || [], d.site.timezone);
            el('wz-db-path').value = d.default_sqlite_path || '';
            if (d.site.hospital_name) el('wz-site-name').value = d.site.hospital_name;
            if (d.site.hospital_code) el('wz-site-code').value = d.site.hospital_code;
            // 阶段续填
            var stageMap = { env: 'env', migrate: 'migrate', site: 'site', admin: 'admin', done: 'finish' };
            show(stageMap[d.stage] || 'env');
            if (d.stage === 'migrate') {
                el('wz-migrate-hint').textContent = '数据库连接已保存，点击开始创建业务数据表。';
            }
        }).catch(function () { /* 已 toast */ });
    }

    /* ---------- 步骤事件（脚本于 body 末尾加载，元素已就绪） ---------- */
    (function bind() {
        bindDbType();

        el('wz-env-next').addEventListener('click', function () { show('database'); });

        el('wz-db-submit').addEventListener('click', function () {
            var payload = {
                type: el('wz-db-type').value,
                path: el('wz-db-path').value.trim(),
                host: el('wz-db-host').value.trim(),
                name: el('wz-db-name').value.trim(),
                user: el('wz-db-user').value.trim(),
                pass: el('wz-db-pass').value
            };
            api('setup/database', payload).then(function () {
                Dom.toast('数据库连接正常', 'success');
                show('migrate');
            }).catch(function () { /* 已 toast */ });
        });

        el('wz-migrate-submit').addEventListener('click', function () {
            api('setup/migrate').then(function (r) {
                el('wz-migrate-hint').textContent = (r.data && r.data.applied > 0)
                    ? '已执行 ' + r.data.applied + ' 个迁移，数据库就绪。'
                    : '数据库已就绪。';
                el('wz-migrate-submit').style.display = 'none';
                el('wz-migrate-next').style.display = 'block';
            }).catch(function () { /* 已 toast */ });
        });
        el('wz-migrate-next').addEventListener('click', function () { show('site'); });

        el('wz-site-submit').addEventListener('click', function () {
            api('setup/site', {
                hospital_name: el('wz-site-name').value.trim(),
                hospital_code: el('wz-site-code').value.trim(),
                timezone: el('wz-site-tz').value
            }).then(function () {
                Dom.toast('站点信息已保存', 'success');
                show('admin');
            }).catch(function () { /* 已 toast */ });
        });

        el('wz-admin-submit').addEventListener('click', function () {
            var user = el('wz-admin-user').value.trim();
            var pass = el('wz-admin-pass').value;
            var pass2 = el('wz-admin-pass2').value;
            if (!user || !pass) { Dom.toast('请填写用户名与密码', 'error'); return; }
            if (pass !== pass2) { Dom.toast('两次密码不一致', 'error'); return; }
            api('setup/admin', { username: user, password: pass, password2: pass2 }).then(function () {
                state.adminUser = user;
                state.adminPass = pass;
                Dom.toast('管理员创建成功', 'success');
                show('finish');
            }).catch(function () { /* 已 toast */ });
        });

        el('wz-finish-submit').addEventListener('click', function () {
            api('setup/finish').then(function () {
                return Auth.login(state.adminUser, state.adminPass);
            }).then(function () {
                window.location.hash = 'admin/departments';
                window.location.reload();
            }).catch(function () { /* 已 toast */ });
        });
    })();

    return { init: init };
})();
