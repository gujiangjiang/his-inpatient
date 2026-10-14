<?php header("Content-Type: text/html; charset=utf-8"); ?>
<div class="page-head flex-between">
    <div>
        <h3 class="page-title">住院病案首页</h3>
        <p class="page-desc">结构化病案首页（编辑 / 打印正反两页）</p>
    </div>
    <div>
        <button class="btn btn-primary" onclick="CaseHome.save()">保存</button>
        <button class="btn" onclick="CaseHome.preview()">打印预览</button>
        <button class="btn" onclick="CaseHome.print()">打印首页</button>
        <button class="btn" onclick="case_front_page_main_init()">刷新</button>
    </div>
</div>

<div class="tabs">
    <button class="tab active" id="cf-tab-1" onclick="cf_tab(1)">住院病案首页（一）</button>
    <button class="tab" id="cf-tab-2" onclick="cf_tab(2)">住院病案首页（二）</button>
</div>

<div id="cf-pane1"></div>
<div id="cf-pane2" class="hidden"></div>

<script>
function cf_tab(n) {
    document.getElementById('cf-pane1').classList.toggle('hidden', n !== 1);
    document.getElementById('cf-pane2').classList.toggle('hidden', n !== 2);
    document.getElementById('cf-tab-1').classList.toggle('active', n === 1);
    document.getElementById('cf-tab-2').classList.toggle('active', n === 2);
}

async function case_front_page_main_init() {
    document.getElementById('main-app').style.display = 'flex';
    document.getElementById('login-container').style.display = 'none';
    const sidebar = await fetch('/js/shared/sidebar_doctor.php').then(r => r.text());
    document.getElementById('sidebar').innerHTML = sidebar;
    CaseHome.init();
}
</script>
