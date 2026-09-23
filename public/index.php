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
    <link rel="stylesheet" href="/css/emr/emr.css">
</head>
<body>
<div id="app">
    <div id="login-container" class="login-container" style="display:none">
        <div class="login-form-wrapper">
            <h1 class="login-title">住院一体化系统</h1>
            <div id="login-box">
                <form id="login-form" class="login-form">
                    <div class="form-group">
                        <input type="text" name="username" placeholder="用户名" autocomplete="username" required>
                    </div>
                    <div class="form-group">
                        <input type="password" name="password" placeholder="密码" autocomplete="current-password" required>
                    </div>
                    <button type="submit" class="login-btn">登录</button>
                </form>
            </div>
            <div id="setup-wizard" style="display:none">
                <h2>系统初始化</h2>
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
                        <span class="switcher-arrow">▾</span>
                    </div>
                    <div id="patient-switcher-panel" class="patient-switcher-panel" style="display:none">
                        <div class="switcher-tabs">
                            <button type="button" class="switcher-tab" data-tab="incoming">添加</button>
                            <button type="button" class="switcher-tab active" data-tab="mine">本人</button>
                            <button type="button" class="switcher-tab" data-tab="dept">科室</button>
                        </div>
                        <div id="switcher-tab-content" class="switcher-tab-content"></div>
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
            <span class="pib-item"><b>姓名</b><i id="pib-name">-</i></span>
            <span class="pib-item"><b>性别</b><i id="pib-gender">-</i></span>
            <span class="pib-item"><b>年龄</b><i id="pib-age">-</i></span>
            <span class="pib-item"><b>科室</b><i id="pib-dept">-</i></span>
            <span class="pib-item"><b>病区</b><i id="pib-ward">-</i></span>
            <span class="pib-item pib-diagnosis"><b>主诊断</b><i id="pib-diagnosis">-</i></span>
            <span class="pib-item"><b>总费用</b><i id="pib-fee">¥ --</i></span>
            <button type="button" class="btn btn-small" onclick="PatientContext.clear()">切换患者</button>
        </div>

        <div class="main-container">
            <!-- 左侧主功能导航栏 (患者选定后激活) -->
            <div id="sidebar" class="sidebar" style="display:none"></div>
            <!-- 未选患者占位 -->
            <div id="patient-placeholder" class="patient-placeholder">
                <div class="placeholder-inner">
                    <div class="placeholder-icon">🏥</div>
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
<script src="/js/login/login.js"></script>
</body>
</html>