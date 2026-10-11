<?php header("Content-Type: text/html; charset=utf-8"); ?>
<div class="page-head">
    <h3 class="page-title">接口管理</h3>
    <p class="page-desc">与门诊一体化系统、PACS 影像系统互通配置（一期：只读查询 + 字典推送）</p>
</div>

<div class="tabs">
    <button class="tab active" id="itg-tab-opd" onclick="admin_integrations_switch('opd')">门诊一体化</button>
    <button class="tab" id="itg-tab-pacs" onclick="admin_integrations_switch('pacs')">PACS 影像</button>
    <button class="tab" id="itg-tab-endpoints" onclick="admin_integrations_switch('endpoints')">对接端点</button>
</div>

<div id="itg-pane-opd">
    <div class="card">
        <div class="card-header">连接状态
            <button class="btn btn-small btn-primary" style="float:right" onclick="admin_integrations_test()">连通性测试</button>
        </div>
        <div class="status-list" id="itg-opd-status"></div>
        <div class="test-result" id="itg-test-result"></div>
    </div>
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
                <div class="hint">门诊一体化系统根地址，自动拼接 /api/fhir/r4（FHIR）与 /api/external/his（业务接口）</div>
            </div>
            <div class="form-group">
                <label class="form-label">FHIR 接口 Token</label>
                <input class="form-control" id="itg-opd-fhir-token" type="password" placeholder="门诊「接口管理 → FHIR 入向」Token" autocomplete="off">
            </div>
            <div class="form-group">
                <label class="form-label">业务接口 Token</label>
                <input class="form-control" id="itg-opd-his-token" type="password" placeholder="门诊「接口管理 → HIS 入向」Token" autocomplete="off">
            </div>
        </div>
        <button class="btn btn-primary" onclick="admin_integrations_save()">保存配置</button>
        <button class="btn btn-success" onclick="admin_integrations_push()">推送 HIS 字典到门诊</button>
    </div>
</div>

<div id="itg-pane-pacs" class="hidden">
    <div class="card">
        <div class="card-header">PACS 阅片配置</div>
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
