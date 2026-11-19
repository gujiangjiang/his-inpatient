<?php header("Content-Type: text/html; charset=utf-8"); ?>
<div class="page-head flex-between">
    <div>
        <h3 class="page-title">接口管理</h3>
        <p class="page-desc">门诊系统 · PACS 影像系统互通（只读查询 + 字典推送 + 住院检验检查单推送）</p>
    </div>
    <button class="btn btn-primary" onclick="admin_integrations_test()">连通性测试</button>
</div>

<div class="card" style="padding:10px 16px">
    <div class="sub-tabs">
        <button class="sub-tab active" id="itg-tab-opd" onclick="admin_integrations_switch('opd')">门诊</button>
        <button class="sub-tab" id="itg-tab-pacs" onclick="admin_integrations_switch('pacs')">PACS 影像</button>
        <button class="sub-tab" id="itg-tab-catalog" onclick="admin_integrations_switch('catalog')">检查/检验列表</button>
        <button class="sub-tab" id="itg-tab-endpoints" onclick="admin_integrations_switch('endpoints')">对接端点</button>
    </div>
</div>

<!-- 门诊：左右两栏（状态/配置/端点/审计/帮助子导航） -->
<div id="itg-pane-opd">
    <div class="pane-wrap">
        <div class="pane-nav">
            <div class="pn-item active" data-pane="overview" onclick="admin_itg_sub('overview')">状态总览</div>
            <div class="pn-item" data-pane="config" onclick="admin_itg_sub('config')">连接配置</div>
            <div class="pn-item" data-pane="audit" onclick="admin_itg_sub('audit')">变更记录</div>
            <div class="pn-item" data-pane="help" onclick="admin_itg_sub('help')">帮助</div>
        </div>
        <div class="pane-main">
            <div class="db-pane" data-pane="overview">
                <div class="card">
                    <div class="card-header">连接状态</div>
                    <div class="status-list" id="itg-opd-status"></div>
                </div>
            </div>
            <div class="db-pane" data-pane="config" style="display:none">
                <div class="card">
                    <div class="card-header">连接配置</div>
                    <div class="form-grid">
                        <div class="form-group colspan-2">
                            <label class="form-label">门诊接口</label>
                            <label class="fs-13"><input type="checkbox" id="itg-opd-enabled"> 启用（住院医生可查看患者门诊记录 / 检验 / 检查）</label>
                        </div>
                        <div class="form-group colspan-2">
                            <label class="form-label">门诊服务地址</label>
                            <input class="form-control" id="itg-opd-base" placeholder="http://127.0.0.1:8000">
                            <div class="hint">门诊系统根地址，自动拼接 /api/fhir/r4（FHIR）与 /api/external/his（业务接口）</div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">FHIR 接口 Token</label>
                            <input class="form-control" id="itg-opd-fhir-token" type="password" placeholder="门诊「接口管理 → HIS 接口」FHIR Token" autocomplete="off">
                        </div>
                        <div class="form-group">
                            <label class="form-label">业务接口 Token</label>
                            <input class="form-control" id="itg-opd-his-token" type="password" placeholder="门诊「接口管理 → HIS 接口」业务 Token" autocomplete="off">
                        </div>
                    </div>
                    <div class="flex gap-8 mt-10">
                        <button class="btn btn-primary" onclick="admin_integrations_save()">保存配置</button>
                        <button class="btn btn-success" onclick="admin_integrations_push()">推送 HIS 字典到门诊</button>
                        <button class="btn" onclick="admin_integrations_retryOrders()">重推失败单据</button>
                    </div>
                </div>
            </div>
            <div class="db-pane" data-pane="audit" style="display:none">
                <div class="card">
                    <div class="card-header">配置变更记录
                        <button class="btn btn-small" style="float:right" onclick="admin_integrations_auditClear()">清空</button>
                    </div>
                    <table class="table">
                        <thead><tr><th>时间</th><th>操作人</th><th>变更内容</th><th>IP</th></tr></thead>
                        <tbody id="itg-audit-body"></tbody>
                    </table>
                </div>
            </div>
            <div class="db-pane" data-pane="help" style="display:none">
                <div class="card">
                    <div class="card-header">对接说明</div>
                    <div class="itg-help-body fs-13">
                        <p>1. <b>门诊服务地址</b>填门诊系统根地址（如 http://127.0.0.1:8000），系统自动拼接 /api/fhir/r4（FHIR）与 /api/external/his（业务接口）。</p>
                        <p>2. <b>两个 Token</b> 在门诊「接口管理 → HIS 接口」中配置并复制。</p>
                        <p>3. FHIR Token 用于患者匹配与门诊记录 / 检验 / 检查 / 处方查询；业务 Token 用于就诊查询与字典推送（需 catalog:sync 权限）。</p>
                        <p>4. <b>PACS 阅片地址模板</b>必须包含 {study_uid} 占位符（如 http://127.0.0.1:8090/?r=viewer&uid={study_uid}）。</p>
                        <p>5. 保存后点击「连通性测试」验证全部服务，测试结果同步到连接状态。</p>
                        <p>6. 医生端入口：患者工作站「门诊记录 / 检验 / 检查」菜单；患者信息栏姓名可直接点击进入就诊记录。</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="itg-pane-pacs" class="hidden">
    <div class="card">
        <div class="card-header">阅片配置</div>
        <div class="form-grid">
            <div class="form-group colspan-2">
                <label class="form-label">影像直链</label>
                <label class="fs-13"><input type="checkbox" id="itg-pacs-enabled"> 启用（检查报告出现「查看影像」按钮）</label>
            </div>
            <div class="form-group colspan-2">
                <label class="form-label">阅片地址模板</label>
                <input class="form-control" id="itg-pacs-viewer" placeholder="http://127.0.0.1:8090/?r=viewer&uid={study_uid}">
                <div class="hint">必须包含 {study_uid} 占位符；StudyUID 来自门诊检查报告的 DICOM 标识</div>
            </div>
        </div>
        <button class="btn btn-primary" onclick="admin_integrations_save()">保存配置</button>
    </div>
    <div class="card">
        <div class="card-header">说明</div>
        <p class="fs-13 text-muted">医生端「门诊检查」列表的「查看影像」按钮，将按模板打开 PACS 免登录阅片直链（新窗口）。本系统不存储影像，仅保存检查标识。</p>
    </div>
</div>

<div id="itg-pane-catalog" class="hidden">
    <div class="card">
        <div class="card-header">检查/检验项目列表（外部接口开启时实时显示）
            <span class="head-stat" id="itg-catalog-source"></span>
        </div>
        <div class="flex-between mb-10">
            <div class="sub-tabs">
                <button class="sub-tab active" data-type="lab" onclick="admin_itg_catalog_type('lab')">检验项目</button>
                <button class="sub-tab" data-type="exam" onclick="admin_itg_catalog_type('exam')">检查项目</button>
            </div>
            <input class="form-control" id="itg-catalog-kw" placeholder="搜索项目名称" style="max-width:240px" oninput="admin_itg_catalog_debounced()">
        </div>
        <table class="table">
            <thead><tr><th>分类</th><th>名称</th><th>单位</th><th>价格</th><th>状态</th></tr></thead>
            <tbody id="itg-catalog-body"></tbody>
        </table>
        <p class="hint" id="itg-catalog-hint"></p>
    </div>
</div>

<div id="itg-pane-endpoints" class="hidden">
    <div class="card">
        <div class="card-header">对接端点登记
            <button class="btn btn-small" style="float:right" onclick="admin_integrations_new()">新增端点</button>
        </div>
        <table class="table">
            <thead><tr><th>名称</th><th>标准</th><th>业务</th><th>地址</th><th>鉴权</th><th>状态</th><th>操作</th></tr></thead>
            <tbody id="integration-table-body"></tbody>
        </table>
        <p class="hint">登记 FHIR / HL7 / HIS / DICOM 标准端点（业务适配器后续版本扩展）。</p>
    </div>
</div>