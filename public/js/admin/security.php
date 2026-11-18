<?php header("Content-Type: text/html; charset=utf-8"); ?>
<div class="page-head">
    <h3 class="page-title">安全与加密</h3>
    <p class="page-desc">登录会话与安全基线（当前实现为只读展示；登录锁定/验证码为后续增强项）</p>
</div>

<div class="card">
    <div class="card-header">登录与会话</div>
    <div class="status-list" id="security-status"></div>
</div>

<div class="card">
    <div class="card-header">安全说明</div>
    <div class="fs-13 text-muted" style="line-height:1.8">
        <p>· 密码使用 <code>password_hash</code>（bcrypt）存储，不存明文。</p>
        <p>· 会话 Cookie 使用 <code>HttpOnly</code> + <code>SameSite</code>，避免 XSS 窃取。</p>
        <p>· 所有 AJAX 接口统一经 <code>Auth</code> 鉴权 + <code>Validator</code> 参数校验，SQL 全部预处理防注入。</p>
        <p>· 管理端配置变更、日志写入均记录操作人，可在「日志中心」追溯。</p>
        <p>· 登录失败锁定 / 图形验证码：当前为演示基线未启用，后续版本可按门诊一体化接入。</p>
    </div>
</div>

<script>
async function admin_security_init() {
    document.getElementById('main-app').style.display = 'flex';
    document.getElementById('login-container').style.display = 'none';
    const sidebar = await fetch('/js/shared/sidebar_admin.php').then(r => r.text());
    document.getElementById('sidebar').innerHTML = sidebar;
    admin_security_load();
}

async function admin_security_load() {
    const box = document.getElementById('security-status');
    const sessionName = 'HIS_SESSION';
    const rows = [
        ['会话名称', sessionName],
        ['会话有效期', '12 小时'],
        ['Cookie 属性', 'HttpOnly · SameSite'],
        ['密码哈希', 'bcrypt (password_hash)'],
        ['SQL 注入防护', 'PDO 预处理参数绑定'],
        ['输出转义', 'Format.escape / e()'],
        ['登录失败锁定', '未启用（后续增强）'],
        ['图形验证码', '未启用（后续增强）']
    ];
    box.innerHTML = rows.map(function (row) {
        return '<div class="status-row"><span class="status-label">' + row[0] + '</span><span class="status-value">' + row[1] + '</span></div>';
    }).join('');
}
</script>