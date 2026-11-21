<script>
/** 设计工具: 插入字段 / 下拉字段(单选/复选) / 标题 / 引用
 *  下拉/复选: 可不含固定标签 (与文字混排), 选项每行一个, 占位描述可自定义 */
function emr_dsnInsertField(withSelect, multi) {
    if (!_emrEditor) return;
    if (!_emrEditor.getCaretPoint()) { Dom.toast('请先在页面中点击要插入的位置', 'error'); return; }
    const title = withSelect ? (multi ? '插入复选框字段' : '插入下拉字段') : '插入字段';
    const content = '<div class="form-group"><label class="emr-prop-radio"><input type="checkbox" id="dsn-label-on" checked> 包含固定标签（在字段前显示文字标签，如「职业史：」）</label></div>'
        + '<div class="form-group" id="dsn-label-row"><label class="form-label">固定标签文字</label>'
        + '<input id="dsn-label" class="form-control" placeholder="如：职业史"></div>'
        + (withSelect
            ? '<div class="form-group"><label class="form-label">选项（每行一个，也可用顿号/逗号分隔；可留空稍后右击配置）</label>'
              + '<textarea id="dsn-opts" class="form-control" rows="4" placeholder="承认&#10;否认&#10;不详"></textarea></div>'
              + '<div class="form-group"><label class="form-label">占位描述（未选择时显示，打印不可见）</label>'
              + '<input id="dsn-ph" class="form-control" value="请选择" placeholder="如：请选择处置措施（可留空）"></div>'
            : '<div class="form-group"><label class="form-label">占位描述（未填写时显示，打印不可见）</label>'
              + '<input id="dsn-ph" class="form-control" placeholder="默认：请输入+标签（可留空）"></div>')
        + (withSelect ? '<p class="emr-tpl-hint">下拉框为独立元素：取消勾选固定标签后可与其他文字混排（如「下拉框」高血压病病史）。</p>' : '')
        + (multi ? '<p class="emr-tpl-hint">复选：书写时可勾选多项，自动以顿号拼接（如 承认、否认）</p>' : '');
    const sub = Dom.modal(title, content, [
        { text: '插入', type: 'primary', onclick: function () {
            const withLabel = sub.querySelector('#dsn-label-on').checked;
            const label = (sub.querySelector('#dsn-label').value || '').trim();
            if (withLabel && !label) { Dom.toast('请输入固定标签文字（或取消勾选「包含固定标签」）', 'error'); return false; }
            const select = withSelect ? emrParseOpts(sub.querySelector('#dsn-opts').value) : [];
            const ph = (sub.querySelector('#dsn-ph').value || '').trim();
            _emrEditor.insertField(label, {
                withLabel: withLabel,
                select: withSelect ? select : null,
                multi: !!multi,
                ph: ph || undefined
            });
        } },
        { text: '取消', type: 'default' }
    ]);
    const onEl = sub.querySelector('#dsn-label-on');
    const labelRow = sub.querySelector('#dsn-label-row');
    const updLabelRow = function () { labelRow.style.display = onEl.checked ? '' : 'none'; };
    onEl.addEventListener('change', updLabelRow);
    updLabelRow();
    sub.querySelector('#dsn-label').focus();
}
function emr_dsnInsertHeading() {
    if (!_emrEditor) return;
    if (!_emrEditor.getCaretPoint()) { Dom.toast('请先在页面中点击要插入的位置', 'error'); return; }
    const content = '<div class="form-group"><label class="form-label">标题文字</label><input id="dsn-h" class="form-control" placeholder="如：体格检查"></div>';
    const sub = Dom.modal('插入标题', content, [
        { text: '插入', type: 'primary', onclick: function () {
            const t = (document.getElementById('dsn-h').value || '').trim();
            if (!t) { Dom.toast('请输入标题文字', 'error'); return false; }
            _emrEditor.insertHeading(t);
        } },
        { text: '取消', type: 'default' }
    ]);
    sub.querySelector('#dsn-h').focus();
}
function emr_dsnInsertRef() {
    if (!_emrEditor) return;
    if (!_emrEditor.getCaretPoint()) { Dom.toast('请先在页面中点击要插入的位置', 'error'); return; }
    const content = '<div class="form-group"><label class="form-label">字段键（其他文书的字段标识）</label>'
        + '<input id="dsn-ref" class="form-control" list="dsn-ref-list" placeholder="如：chief_complaint_val（主诉）">'
        + '<datalist id="dsn-ref-list"><option value="p_name">姓名</option><option value="p_age">年龄</option><option value="p_gender">性别</option><option value="chief_complaint_val">主诉</option><option value="present_illness_val">现病史</option><option value="past_history_val">既往史</option><option value="special_exam_val">专科检查</option><option value="aux_exam_val">辅助检查</option><option value="vital_bmi_value">BMI</option></datalist></div>'
        + '<div class="form-group"><label class="form-label">无值时的占位文字</label><input id="dsn-ref-fb" class="form-control" value="＿＿"></div>';
    const sub = Dom.modal('插入字段引用', content, [
        { text: '插入', type: 'primary', onclick: function () {
            const k = (document.getElementById('dsn-ref').value || '').trim();
            const fb = (document.getElementById('dsn-ref-fb').value || '＿＿').trim();
            if (!k) { Dom.toast('请输入字段键', 'error'); return false; }
            _emrEditor.insertRef(k, fb);
        } },
        { text: '取消', type: 'default' }
    ]);
    sub.querySelector('#dsn-ref').focus();
}

/** 科室显示名: 全院通用(NULL) / 实时科室名 (id 关联, 随管理员更名同步) / 已删除科室兜底 */
function emrDeptNameOf(t) {
    if (t.department_id == null) return '全院通用';
    return t.department_name ? t.department_name : ('科室#' + t.department_id + '（已弃用）');
}
</script>
