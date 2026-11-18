<?php
// public/index.php - 唯一入口 (SPA 壳 + API 路由)
// FrankenPHP/Caddy php_server: / 与不存在的路径都会执行本文件
// /api/* → 交由 API 处理; 静态资源缺失 → 404; 其他 → 输出 SPA 页面

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// PWA/品牌公开资源（扩展名检查与认证之前分发）
if (in_array($uri, ['/manifest', '/service-worker', '/branding/icon'], true)) {
    require_once __DIR__ . '/pwa.php';
    if (pwa_dispatch($uri)) return;
}

if (strpos($uri, '/api/') === 0) {
    require __DIR__ . '/../api/index.php';
    return;
}

// 静态资源 (.js/.css/.png/...) 缺失时返回 404, 避免 SPA 页面对脚本报 "Unexpected token '<'"
if (pathinfo($uri, PATHINFO_EXTENSION) !== '' && !is_file(__DIR__ . $uri)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo '404 Not Found';
    return;
}

require_once __DIR__ . '/../api/config.php';
require_once __DIR__ . '/../api/core/db.php';
require_once __DIR__ . '/../api/core/branding.php';
require_once __DIR__ . '/icons/icon.php';
require_once __DIR__ . '/assets.php';
$brand = branding_public();
$esc = function ($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); };
$pageTitle = $brand['hospital_name'] . ($brand['hospital_name2'] !== '' ? ' - ' . $brand['hospital_name2'] : ' - 住院一体化系统');
header('Cache-Control: no-store');
?><!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#149a9a">
    <meta name="application-name" content="<?= $esc($brand['hospital_name']) ?>">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <link rel="manifest" id="pwa-manifest" href="/manifest?v=<?= $esc($brand['brand_revision']) ?>">
    <link rel="icon" id="app-favicon" type="image/png" href="<?= $esc($brand['icon_192_url']) ?>">
    <link rel="apple-touch-icon" id="app-touch-icon" href="<?= $esc($brand['icon_192_url']) ?>">
    <title><?= $esc($pageTitle) ?></title>
<?php Asset::css(); ?>
</head>
<body>
<div id="app">
    <div id="login-container" class="login-container" style="display:none">
        <div class="login-deco login-deco-1"></div>
        <div class="login-deco login-deco-2"></div>
        <div class="login-deco login-deco-3"></div>
        <div class="login-form-wrapper">
            <div class="login-brand">
                <div class="login-logo"><img id="login-hospital-logo" src="<?= $esc($brand['logo_url']) ?>" alt="医院 Logo"></div>
                <div class="brand-name-lockup login-name-lockup">
                    <h1 class="brand-name-line login-title" id="login-title"><?= $esc($brand['hospital_name']) ?></h1>
                    <p class="brand-name-line login-second-name" id="login-second-name"<?= $brand['hospital_name2'] === '' ? ' hidden' : '' ?>><?= $esc($brand['hospital_name2']) ?></p>
                </div>
                <p class="login-subtitle">HOSPITAL INPATIENT INFORMATION SYSTEM</p>
            </div>
            <div id="login-box">
                <form id="login-form" class="login-form">
                    <div class="form-group">
                        <label class="login-label">用户名</label>
                        <input type="text" name="username" id="username" placeholder="请输入用户名" autocomplete="username" required>
                    </div>
                    <div class="form-group">
                        <label class="login-label">密码</label>
                        <input type="password" name="password" id="password" placeholder="请输入密码" autocomplete="current-password" required>
                    </div>
                    <div class="form-group login-captcha-group" id="captcha-group" style="display:none">
                        <label class="login-label">验证码</label>
                        <div class="login-captcha-box">
                            <input type="text" id="login-captcha" placeholder="请输入右侧字符" maxlength="4" autocomplete="off">
                            <span id="login-captcha-svg" title="看不清？点击刷新" style="cursor:pointer;flex:0 0 auto;width:110px;height:40px;display:flex;align-items:center;justify-content:center;border-left:1px solid #e8edf3"></span>
                        </div>
                    </div>
                    <button type="submit" class="login-btn">登 录</button>
                </form>
            </div>
            <div id="setup-wizard" style="display:none">
                <h2 class="login-setup-title">系统初始化</h2>
                <div class="wizard-steps" id="wizard-steps">
                    <span class="wizard-step" data-step="env">环境</span>
                    <span class="wizard-step" data-step="database">数据库</span>
                    <span class="wizard-step" data-step="migrate">初始化</span>
                    <span class="wizard-step" data-step="site">站点</span>
                    <span class="wizard-step" data-step="admin">管理员</span>
                    <span class="wizard-step" data-step="finish">完成</span>
                </div>
                <div class="wizard-body">
                    <!-- ① 环境检测 -->
                    <div class="wizard-pane" data-pane="env" style="display:none">
                        <ul class="wizard-check" id="wz-env"></ul>
                        <button type="button" class="login-btn" id="wz-env-next">下一步</button>
                    </div>
                    <!-- ② 数据库配置 -->
                    <div class="wizard-pane" data-pane="database" style="display:none">
                        <div class="form-group">
                            <label class="login-label">数据库类型</label>
                            <select id="wz-db-type" class="wizard-input">
                                <option value="sqlite">SQLite（内置文件，推荐）</option>
                                <option value="mysql">MySQL / MariaDB</option>
                                <option value="postgresql">PostgreSQL</option>
                            </select>
                        </div>
                        <div class="form-group wz-db-sqlite">
                            <input type="text" id="wz-db-path" placeholder="数据文件路径">
                        </div>
                        <div class="wz-db-sql" style="display:none">
                            <div class="form-group"><input type="text" id="wz-db-host" placeholder="主机（如 127.0.0.1）"></div>
                            <div class="form-group"><input type="text" id="wz-db-name" placeholder="数据库名"></div>
                            <div class="form-group"><input type="text" id="wz-db-user" placeholder="用户名"></div>
                            <div class="form-group"><input type="password" id="wz-db-pass" placeholder="密码"></div>
                        </div>
                        <button type="button" class="login-btn" id="wz-db-submit">保存并测试连接</button>
                    </div>
                    <!-- ③ 数据库初始化 -->
                    <div class="wizard-pane" data-pane="migrate" style="display:none">
                        <p class="wizard-hint" id="wz-migrate-hint">将创建全部业务数据表（幂等，可重复执行）。</p>
                        <button type="button" class="login-btn" id="wz-migrate-submit">开始初始化</button>
                        <button type="button" class="wizard-sub-link" id="wz-migrate-next" style="display:none">下一步</button>
                    </div>
                    <!-- ④ 站点信息 -->
                    <div class="wizard-pane" data-pane="site" style="display:none">
                        <div class="form-group"><input type="text" id="wz-site-name" placeholder="医院名称"></div>
                        <div class="form-group"><input type="text" id="wz-site-name2" placeholder="医院第二名称（选填）"></div>
                        <div class="form-group"><input type="text" id="wz-site-code" placeholder="组织机构代码"></div>
                        <div class="form-group">
                            <label class="login-label">全站时区</label>
                            <select id="wz-site-tz" class="wizard-input"></select>
                        </div>
                        <p class="wizard-hint">存储与对外接口均为标准 UTC；此处仅决定全站显示时区。</p>
                        <button type="button" class="login-btn" id="wz-site-submit">保存</button>
                    </div>
                    <!-- ⑤ 管理员账号 -->
                    <div class="wizard-pane" data-pane="admin" style="display:none">
                        <div class="form-group"><input type="text" id="wz-admin-user" placeholder="管理员用户名（3-32 位字母/数字/下划线）"></div>
                        <div class="form-group"><input type="password" id="wz-admin-pass" placeholder="密码（至少 6 位）"></div>
                        <div class="form-group"><input type="password" id="wz-admin-pass2" placeholder="重复密码"></div>
                        <button type="button" class="login-btn" id="wz-admin-submit">创建管理员</button>
                    </div>
                    <!-- ⑥ 完成 -->
                    <div class="wizard-pane" data-pane="finish" style="display:none">
                        <p class="wizard-hint" id="wz-finish-hint">全部配置已完成，点击进入系统。</p>
                        <button type="button" class="login-btn" id="wz-finish-submit">完成初始化并进入系统</button>
                    </div>
                </div>
            </div>
            <div class="login-foot">仅限授权医疗机构内部使用</div>
        </div>
    </div>

    <div id="main-app" style="display:none">
        <!-- 顶部全局导航栏 -->
        <div class="topbar">
            <div class="topbar-left">
                <div class="hospital-brand">
                    <img id="hospital-logo" class="hospital-logo" src="<?= $esc($brand['logo_url']) ?>" alt="医院 Logo">
                    <span class="brand-name-lockup hospital-title-wrap">
                        <span id="hospital-name" class="brand-name-line hospital-name"><?= $esc($brand['hospital_name']) ?></span>
                        <small id="hospital-name2" class="brand-name-line hospital-name2"<?= $brand['hospital_name2'] === '' ? ' hidden' : '' ?>><?= $esc($brand['hospital_name2']) ?></small>
                    </span>
                </div>
                <!-- 患者上下文切换浮动面板 -->
                <div id="patient-switcher" class="patient-switcher" style="display:none">
                    <div id="patient-switcher-trigger" class="patient-switcher-trigger">
                        <span id="current-patient-summary">请选择患者</span>
                        <?= icon('common/arrows', 'caret-down', 10, 'switcher-arrow') ?>
                    </div>
                    <div id="patient-switcher-panel" class="patient-switcher-panel" style="display:none">
                        <div class="switcher-menu" id="switcher-menu">
                            <div class="switcher-menu-item" data-action="incoming">添加患者</div>
                            <div class="switcher-menu-item" data-action="mine">主管患者</div>
                            <div class="switcher-menu-item" data-action="dept">科室患者+
                                <?= icon('common/arrows', 'caret-right', 10, 'arrow', 'id="switcher-dept-caret"') ?>
                            </div>
                            <div id="switcher-dept-sub" class="switcher-menu-sub" style="display:none"></div>
                            <div class="switcher-menu-item" data-action="toggle-size">大小卡切换</div>
                            <div class="switcher-menu-item" data-action="discharged">出院患者</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="topbar-right">
                <span id="user-info"></span>
                <div class="notify-wrap">
                    <button type="button" class="notify-bell" id="notify-bell" title="消息通知"><?= icon('common/actions', 'bell', 16) ?><span class="notify-badge" id="notify-badge" style="display:none">0</span></button>
                    <div class="notify-panel" id="notify-panel" style="display:none">
                        <div class="notify-head"><span>消息</span><a href="javascript:;" id="notify-readall">全部已读</a></div>
                        <div class="notify-list" id="notify-list"></div>
                    </div>
                </div>
                <button class="btn btn-small" onclick="Auth.logout()">退出</button>
            </div>
        </div>

        <!-- 患者信息栏 (选择患者后显示, 顶部栏下横向条) -->
        <div id="patient-info-bar" class="patient-info-bar" style="display:none">
            <span class="pib-bed level-3" id="pib-bed" title="返回患者列表" onclick="navigateTo('patients','patient_list')">-</span>
            <span class="pib-name" id="pib-name" title="查看住院/门诊记录" style="cursor:pointer" onclick="navigateTo('outpatient','records')">-</span>
            <span class="pib-sub" id="pib-sub">-</span>
            <span class="pib-item"><b>入院</b><i id="pib-days">-</i></span>
            <span class="pib-item"><b>费用</b><i class="pib-fee" id="pib-fee">¥ --</i></span>
            <span class="pib-item pib-diagnosis"><b>诊断</b><i id="pib-diagnosis">-</i></span>
            <span class="pib-actions">
                <button type="button" class="btn btn-small" id="pib-switch-btn" onclick="PatientQuickSwitch.open(event)">切换患者</button>
            </span>
        </div>

        <div class="main-container">
            <!-- 左侧主功能导航栏 (患者选定后激活) -->
            <div id="sidebar" class="sidebar" style="display:none"></div>
            <!-- 未选患者占位 -->
            <div id="patient-placeholder" class="patient-placeholder">
                <div class="placeholder-inner">
                    <div class="placeholder-icon"><?= icon('medical/objects', 'hospital-door', 72) ?></div>
                    <p class="placeholder-title">请先选择患者</p>
                    <p class="placeholder-hint">点击左上角「请选择患者」，从 添加/本人/科室 三个维度选择患者进入工作站</p>
                </div>
            </div>
            <!-- 主视图区 -->
            <main class="main-content" id="app-content" style="display:none"></main>
        </div>
    </div>
</div>
<script>window.HIS_APP_VERSION = <?= json_encode(APP_VERSION) ?>; window.HIS_BRAND = <?= json_encode(['hospital_name' => $brand['hospital_name'], 'hospital_name2' => $brand['hospital_name2']], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;</script>
<?php Asset::js(); ?>
</body>
</html>
