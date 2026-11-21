<?php header("Content-Type: text/html; charset=utf-8"); require_once __DIR__ . '/../../icons/icon.php'; ?>
<div class="emr-station">
    <!-- 二级左栏: 病历分类树 + 文书目录 -->
    <div class="emr-tree" id="emr-tree">
        <div class="emr-tree-header" id="emr-tree-header">
            <button type="button" class="btn btn-small btn-primary emr-tree-toggle" id="emr-tree-toggle" onclick="emr_treeToggle()" title="关闭病历树">收起</button>
            <span id="emr-tree-title">病历文书</span>
        </div>
        <div id="emr-category-tree" class="emr-category-tree"></div>
        <div id="emr-design-panel" class="emr-dsn-panel" style="display:none">
            <div class="emr-dsn-sec">
                <div class="emr-dsn-head" id="dsn-sec-field"><span class="emr-dsn-caret"></span>插入字段</div>
                <div class="emr-dsn-sub" id="dsn-fields" style="display:none"></div>
            </div>
            <div class="emr-dsn-sec">
                <div class="emr-dsn-head" id="dsn-sec-fixed"><span class="emr-dsn-caret"></span>固定元素</div>
                <div class="emr-dsn-sub" id="dsn-fixed" style="display:none"></div>
            </div>
            <div class="emr-dsn-sec">
                <div class="emr-dsn-head" id="dsn-sec-diag"><span class="emr-dsn-caret"></span>诊断引用</div>
                <div class="emr-dsn-sub" id="dsn-diag" style="display:none">
                    <div class="emr-dsn-field" data-diag="admission">入院诊断（写作时自动填入）</div>
                    <div class="emr-dsn-field" data-diag="discharge">出院诊断（写作时自动填入）</div>
                </div>
            </div>
            <div class="emr-dsn-sec">
                <div class="emr-dsn-head" id="dsn-sec-table"><span class="emr-dsn-caret"></span>插入表格</div>
                <div class="emr-dsn-sub" id="dsn-table" style="display:none">
                    <div class="emr-tblpick" id="emr-tblpick"></div>
                    <div class="emr-tblpick-label" id="emr-tblpick-label">移入选择行列（拖动框线可调列宽/行高）</div>
                </div>
            </div>
            <div class="emr-dsn-sec">
                <div class="emr-dsn-head" id="dsn-sec-select"><span class="emr-dsn-caret"></span>插入下拉字段</div>
                <div class="emr-dsn-sub" id="dsn-select" style="display:none">
                    <div class="emr-dsn-field" data-sel="single">单选框（下拉选择）</div>
                    <div class="emr-dsn-field" data-sel="multi">复选框（多选、顿号拼接）</div>
                </div>
            </div>
            <div class="emr-dsn-sec">
                <div class="emr-dsn-head" id="dsn-sec-calc"><span class="emr-dsn-caret"></span>插入自动计算项</div>
                <div class="emr-dsn-sub" id="dsn-calc" style="display:none">
                    <div class="emr-dsn-field" data-calc="custom" title="按字段代码编写公式（如 BMI = 体重 / (身高/100)^2），书写时自动计算">自动计算项（公式组合字段代码，如 BMI）</div>
                </div>
            </div>
            <div class="emr-dsn-sec">
                <div class="emr-dsn-head" id="dsn-sec-heading"><span class="emr-dsn-caret"></span>插入标题</div>
                <div class="emr-dsn-sub" id="dsn-heading" style="display:none">
                    <div class="emr-dsn-field" data-heading="1" title="居中加粗、上方带分隔线的段落标题（如「体格检查」）">标题（居中加粗、上带分隔线）</div>
                </div>
            </div>
            <div class="emr-dsn-sec">
                <div class="emr-dsn-head" id="dsn-sec-ref"><span class="emr-dsn-caret"></span>插入字段引用</div>
                <div class="emr-dsn-sub" id="dsn-ref" style="display:none">
                    <div class="emr-dsn-field" data-ref="1" title="自动调用患者其他文书的字段值（如【姓名】【主诉】【既往史】）">字段引用（自动调用其他文书字段值）</div>
                </div>
            </div>
        </div>
        <div class="emr-tree-foot" id="emr-tree-foot">
            <button type="button" class="btn btn-small btn-primary" id="btn-print-batch" title="病历文书批量打印（按分类勾选）">打印</button>
            <button type="button" class="btn btn-small" id="btn-template-mgr">模板</button>
            <button type="button" class="btn btn-small" id="btn-content-mgr">内容</button>
            <button type="button" class="btn btn-small" id="btn-maint-mgr" title="病历维护申请（本人申请与审核状态）">维护</button>
        </div>
    </div>
    <!-- 二级主视图: 字处理病历编辑区 (类 Word 整页画布) -->
    <div class="emr-editor-pane">
        <div id="emr-editor-empty" class="emr-editor-empty">
            <p>请从左侧选择病历文书，或点击分类后的 <b>＋</b> 从模板新建</p>
            <button type="button" class="btn btn-small btn-primary emr-tree-openbtn" id="emr-tree-openbtn" onclick="emr_treeOpen()" style="display:none">打开病历树</button>
        </div>
        <div id="emr-editor" class="emr-editor" style="display:none">
            <div class="emr-toolbar" id="emr-toolbar">
                <button type="button" class="btn btn-small emr-tree-open" id="emr-tree-openbar" onclick="emr_treeOpen()" title="病历文书目录" style="display:none"><?= icon('common/arrows', 'caret-right', 12) ?> 病历</button>
                <button type="button" class="btn btn-small fmt-btn fmt-b" id="btn-bold" title="加粗">B</button>
                <button type="button" class="btn btn-small fmt-btn fmt-i" id="btn-italic" title="斜体">I</button>
                <button type="button" class="btn btn-small fmt-btn fmt-u" id="btn-underline" title="下划线">U</button>
                <button type="button" class="btn btn-small fmt-btn fmt-s" id="btn-strike" title="删除线">S</button>
                <span class="emr-tb-sep"></span>
                <button type="button" class="btn btn-small fmt-btn" id="btn-align-l" title="左对齐"><?= icon('common/editor', 'align-left', 15) ?></button>
                <button type="button" class="btn btn-small fmt-btn" id="btn-align-c" title="居中"><?= icon('common/editor', 'align-center', 15) ?></button>
                <button type="button" class="btn btn-small fmt-btn" id="btn-align-r" title="右对齐"><?= icon('common/editor', 'align-right', 15) ?></button>
                <button type="button" class="btn btn-small fmt-btn" id="btn-align-j" title="两端对齐"><?= icon('common/editor', 'align-justify', 15) ?></button>
                <span class="emr-tb-sep"></span>
                <button type="button" class="btn btn-small fmt-btn" id="btn-undo" title="撤销 (Ctrl+Z)"><?= icon('common/editor', 'undo', 15) ?></button>
                <button type="button" class="btn btn-small fmt-btn" id="btn-redo" title="重做 (Ctrl+Y)"><?= icon('common/editor', 'redo', 15) ?></button>
                <span class="emr-tb-spacer"></span>
                <button type="button" class="btn btn-small btn-success" id="btn-save">保存草稿</button>
                <button type="button" class="btn btn-small" id="btn-print">打印</button>
                <button type="button" class="btn btn-small" id="btn-versions">版本</button>
                <button type="button" class="btn btn-small btn-primary" id="btn-sign">医师签名</button>
            </div>
            <div class="emr-toolbar" id="emr-design-bar" style="display:none">
                <span class="emr-design-name" id="emr-design-name"></span>
                <span class="emr-dsn-tip">从左侧「模板设计」面板插入字段与元素；右击正文元素可修改属性</span>
                <span class="emr-tb-spacer"></span>
                <button type="button" class="btn btn-small" id="btn-dsn-import" title="从模板文件导入（新建模板）"><?= icon('common/editor', 'import', 14) ?> 导入</button>
                <button type="button" class="btn btn-small" id="btn-dsn-export" title="导出当前模板为文件"><?= icon('common/editor', 'export', 14) ?> 导出</button>
                <button type="button" class="btn btn-small btn-success" id="btn-dsn-save">保存模板</button>
                <button type="button" class="btn btn-small" id="btn-dsn-exit">退出设计</button>
            </div>
            <div id="emr-editor-scroll" class="emr-editor-scroll">
                <div class="emr-editor-page" id="emr-editor-page">
                    <div id="emr-editor-canvas"></div>
                </div>
            </div>
            <div id="emr-editor-status" class="emr-statusbar"><span id="emr-doc-status" class="emr-doc-status"></span><span id="emr-sb-savetag" class="emr-sb-saved">已保存</span><span id="emr-save-state" class="emr-sb-state"></span><button type="button" class="emr-refp-btn" id="btn-ref-panel" title="引用病历/检验/检查/医嘱/既往/内容">引用</button><span id="emr-sb-count" class="emr-sb-count">字数 0</span></div>
            <!-- 引用面板: 从状态栏向上弹出 (病历/检验/检查/医嘱/既往/内容) -->
            <div id="emr-ref-panel" class="emr-refp" style="display:none">
                <div class="emr-refp-head">
                    <div class="emr-refp-tabs">
                        <span class="emr-refp-tab active" data-tab="doc">病历</span>
                        <span class="emr-refp-tab" data-tab="lab">检验</span>
                        <span class="emr-refp-tab" data-tab="exam">检查</span>
                        <span class="emr-refp-tab" data-tab="order">医嘱</span>
                        <span class="emr-refp-tab" data-tab="past">既往</span>
                        <span class="emr-refp-tab" data-tab="content">内容</span>
                    </div>
                    <button type="button" class="btn btn-small btn-primary" id="emr-refp-write">写回病历</button>
                    <button type="button" class="btn btn-small" id="emr-refp-close">收起</button>
                </div>
                <div class="emr-refp-body">
                    <div class="emr-refp-left" id="emr-refp-left"></div>
                    <div class="emr-refp-right" id="emr-refp-right">
                        <div class="emr-refp-empty">从左侧选择一条记录，右侧显示内容；可全选或选中部分文字后点击「写回病历」</div>
                    </div>
                </div>
            </div>
            <div id="emr-signature-footer" class="emr-signature-footer" style="display:none"></div>
        </div>
    </div>
</div>

<?php include __DIR__ . "/station/init.php"; include __DIR__ . "/station/design.php"; include __DIR__ . "/station/tplio.php"; include __DIR__ . "/station/calc.php"; include __DIR__ . "/station/managers.php"; include __DIR__ . "/station/core.php"; include __DIR__ . "/station/refpanel.php"; include __DIR__ . "/station/maint.php"; include __DIR__ . "/station/host.php";
