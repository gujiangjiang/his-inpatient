<?php header("Content-Type: text/html; charset=utf-8"); ?>
<div class="emr-station">
    <!-- 二级左栏: 病历分类树 + 文书目录 -->
    <div class="emr-tree">
        <div class="emr-tree-header" id="emr-tree-header">病历文书</div>
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
            <div class="emr-dsn-item" id="btn-dsn-heading">插入标题</div>
            <div class="emr-dsn-item" id="btn-dsn-ref">插入字段引用</div>
        </div>
        <div class="emr-tree-foot" id="emr-tree-foot">
            <button type="button" class="btn btn-small" id="btn-template-mgr">模板</button>
            <button type="button" class="btn btn-small" id="btn-content-mgr">内容</button>
        </div>
    </div>
    <!-- 二级主视图: 字处理病历编辑区 (类 Word 整页画布) -->
    <div class="emr-editor-pane">
        <div id="emr-editor-empty" class="emr-editor-empty">
            <p>请从左侧选择病历文书，或点击分类后的 <b>＋</b> 从模板新建</p>
        </div>
        <div id="emr-editor" class="emr-editor" style="display:none">
            <div class="emr-toolbar" id="emr-toolbar">
                <button type="button" class="btn btn-small fmt-btn fmt-b" id="btn-bold" title="加粗">B</button>
                <button type="button" class="btn btn-small fmt-btn fmt-i" id="btn-italic" title="斜体">I</button>
                <button type="button" class="btn btn-small fmt-btn fmt-u" id="btn-underline" title="下划线">U</button>
                <button type="button" class="btn btn-small fmt-btn fmt-s" id="btn-strike" title="删除线">S</button>
                <span class="emr-tb-sep"></span>
                <button type="button" class="btn btn-small" id="btn-align-l" title="左对齐">左</button>
                <button type="button" class="btn btn-small" id="btn-align-c" title="居中">中</button>
                <button type="button" class="btn btn-small" id="btn-align-r" title="右对齐">右</button>
                <button type="button" class="btn btn-small" id="btn-align-j" title="两端对齐">两</button>
                <span class="emr-tb-sep"></span>
                <button type="button" class="btn btn-small" id="btn-undo" title="撤销 (Ctrl+Z)">撤销</button>
                <button type="button" class="btn btn-small" id="btn-redo" title="重做 (Ctrl+Y)">重做</button>
                <span class="emr-tb-spacer"></span>
                <button type="button" class="btn btn-small btn-success" id="btn-save">保存草稿</button>
                <button type="button" class="btn btn-small" id="btn-print">打印</button>
                <button type="button" class="btn btn-small" id="btn-template">存为模板</button>
                <button type="button" class="btn btn-small" id="btn-versions">版本</button>
                <button type="button" class="btn btn-small btn-primary" id="btn-sign">医师签名</button>
            </div>
            <div class="emr-toolbar" id="emr-design-bar" style="display:none">
                <span class="emr-design-name" id="emr-design-name"></span>
                <span class="emr-dsn-tip">从左侧「模板设计」面板插入字段与元素；右击正文元素可修改属性</span>
                <span class="emr-tb-spacer"></span>
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

