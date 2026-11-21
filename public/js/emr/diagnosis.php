<?php header("Content-Type: text/html; charset=utf-8"); ?>
<div class="page-head flex-between">
    <div>
        <h3 class="page-title">诊断</h3>
        <p class="page-desc">患者诊断分类管理、诊断同步与诊断符合情况（点击行内「＋」添加）</p>
    </div>
    <div>
        <button class="btn" onclick="emr_diagnosis_sync('discharge')">出院诊断同步</button>
        <button class="btn" onclick="emr_diagnosis_sync('admission')">入院诊断同步</button>
        <button class="btn" onclick="emr_diagnosis_load()">刷新</button>
    </div>
</div>

<div class="card">
    <div class="card-header">常用诊断 <span class="fs-12 text-muted">点击模板可打开添加窗并自动填入</span></div>
    <div class="apply-panels" id="diag-tpl-box"></div>
</div>

<div class="diag-layout">
    <div class="card diag-left" id="diag-rows"></div>
    <div class="card diag-right">
        <div class="card-header">诊断符合情况</div>
        <div id="diag-match-box"></div>
    </div>
</div>

<div class="diag-pop hidden" id="diag-pop">
    <input class="form-control" id="diag-pop-kw" placeholder="搜索诊断 / ICD-10 编码 / 拼音首字母"
        oninput="emr_diag_pop_search(this.value)" onkeydown="emr_diag_pop_key(event)">
    <div class="diag-pop-res" id="diag-pop-res"></div>
    <div class="diag-pop-form">
        <div class="form-group" style="margin-bottom:8px">
            <label class="form-label">已选诊断</label>
            <div id="diag-pop-sel" class="diag-pop-empty">搜索并选择诊断，或直接输入名称「保存」</div>
        </div>
        <div class="form-group" style="margin-bottom:8px">
            <label class="form-label">分类</label>
            <select id="diag-pop-cat" class="form-control" data-spicker="off"></select>
        </div>
        <div class="form-group" style="margin-bottom:8px">
            <label class="form-label">备注（选填）</label>
            <input id="diag-pop-note" class="form-control">
        </div>
        <div class="flex-between">
            <label class="fs-13"><input type="checkbox" id="diag-pop-tpl"> 存为常用</label>
            <div>
                <button class="btn btn-small" onclick="emr_diag_pop_close()">取消</button>
                <button class="btn btn-small btn-primary" onclick="emr_diag_pop_save()">保存</button>
            </div>
        </div>
    </div>
</div>

<?php /* 诊断管理 (聚合入口): 列表/分类/模板 + ICD 搜索弹窗 */ ?>
<?php include __DIR__ . '/diagnosis/list.php'; ?>
<?php include __DIR__ . '/diagnosis/search_pop.php'; ?>
