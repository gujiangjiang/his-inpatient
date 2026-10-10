<?php
// public/index.php - 唯一入口 (SPA 壳 + API 路由)
// FrankenPHP/Caddy php_server: / 与不存在的路径都会执行本文件
// /api/* → 交由 API 处理; 静态资源缺失 → 404; 其他 → 输出 SPA 页面

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

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

require_once __DIR__ . '/icons/icon.php';
?><!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HIS 住院一体化系统</title>
    <link rel="stylesheet" href="/lib/quill/quill.snow.css">
    <link rel="stylesheet" href="/css/core/reset.css">
    <link rel="stylesheet" href="/css/core/layout.css">
    <link rel="stylesheet" href="/css/login/login.css">
    <link rel="stylesheet" href="/css/doctor/doctor.css">
    <link rel="stylesheet" href="/css/doctor/order_station.css">
    <link rel="stylesheet" href="/css/doctor/patient_wall.css">
    <link rel="stylesheet" href="/css/emr/emr.css">
</head>
<body>
<div id="app">
    <div id="login-container" class="login-container" style="display:none">
        <div class="login-deco login-deco-1"></div>
        <div class="login-deco login-deco-2"></div>
        <div class="login-deco login-deco-3"></div>
        <div class="login-form-wrapper">
            <div class="login-brand">
                <div class="login-logo"><?= icon('medical/objects', 'hospital', 30) ?></div>
                <h1 class="login-title" id="login-title">住院一体化系统</h1>
                <p class="login-subtitle">HOSPITAL INPATIENT INFORMATION SYSTEM</p>
            </div>
            <div id="login-box">
                <form id="login-form" class="login-form">
                    <div class="form-group">
                        <label class="login-label">用户名</label>
                        <input type="text" name="username" placeholder="请输入用户名" autocomplete="username" required>
                    </div>
                    <div class="form-group">
                        <label class="login-label">密码</label>
                        <input type="password" name="password" placeholder="请输入密码" autocomplete="current-password" required>
                    </div>
                    <button type="submit" class="login-btn">登 录</button>
                </form>
            </div>
            <div id="setup-wizard" style="display:none">
                <h2 class="login-setup-title">系统初始化</h2>
                <div class="setup-form">
                    <div class="form-group">
                        <input type="text" name="hospital_name" placeholder="医院名称" required>
                    </div>
                    <div class="form-group">
                        <input type="text" name="hospital_code" placeholder="组织机构代码" required>
                    </div>
                    <div class="form-group">
                        <input type="text" name="username" placeholder="管理员用户名" required>
                    </div>
                    <div class="form-group">
                        <input type="password" name="password" placeholder="管理员密码" required>
                    </div>
                    <div class="form-group">
                        <input type="password" name="password2" placeholder="重复密码" required>
                    </div>
                    <button type="button" id="setup-btn" class="login-btn">完成初始化</button>
                </div>
            </div>
            <div class="login-foot">仅限授权医疗机构内部使用</div>
        </div>
    </div>

    <div id="main-app" style="display:none">
        <!-- 顶部全局导航栏 -->
        <div class="topbar">
            <div class="topbar-left">
                <span id="hospital-name" class="hospital-name">开源通用医院信息系统</span>
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
                <button class="btn btn-small" onclick="Auth.logout()">退出</button>
            </div>
        </div>

        <!-- 患者信息栏 (选择患者后显示, 顶部栏下横向条) -->
        <div id="patient-info-bar" class="patient-info-bar" style="display:none">
            <span class="pib-bed level-3" id="pib-bed" title="返回患者列表" onclick="navigateTo('patients','patient_list')">-</span>
            <span class="pib-name" id="pib-name">-</span>
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
<script src="/lib/quill/quill.min.js"></script>
<script src="/lib/html2pdf/html2pdf.bundle.min.js"></script>
<script src="/js/core/app.js"></script>
<script src="/icons/js.php"></script>
<script src="/js/core/format.js"></script>
<script src="/js/core/ajax.js"></script>
<script src="/js/core/auth.js"></script>
<script src="/js/core/dom.js"></script>
<script src="/js/core/components.js"></script>
<script src="/js/core/rich_editor.js"></script>
<script src="/js/core/template_picker.js"></script>
<script src="/js/core/icd_search.js"></script>
<script src="/js/core/pdf_generator.js"></script>
<script src="/js/core/print_helper.js"></script>
<script src="/js/core/patient_context.js"></script>
<script src="/js/core/patient_switcher.js"></script>
<script src="/js/core/patient_quick_switch.js"></script>
<script src="/js/orders/drug_search.js"></script>
<script src="/js/orders/order_editor.js"></script>
<script src="/js/orders/order_quickadd.js"></script>
<script src="/js/orders/order_actions.js"></script>
<script src="/js/orders/order_sets.js"></script>
<script src="/js/login/login.js"></script>
</body>
</html>