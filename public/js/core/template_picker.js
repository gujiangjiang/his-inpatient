// public/js/core/template_picker.js - 共用模板选择弹窗
const TemplatePicker = {
    select(recordType, callback) {
        AjaxLoader.api('emr/template-list?record_type=' + recordType, { silent: true })
            .then(result => {
                const templates = result.data || [];
                if (templates.length === 0) {
                    callback(null);
                    return;
                }
                let optionsHtml = '';
                templates.forEach(t => {
                    optionsHtml += '<option value="' + t.id + '">' + Format.escape(t.name) + ' (使用 ' + t.usage_count + ' 次)</option>';
                });
                const content = `
                    <div class="form-group">
                        <select id="template-select" class="form-control">
                            <option value="">选择模板</option>
                            ${optionsHtml}
                        </select>
                    </div>
                `;
                Dom.modal('从模板插入', content, [
                    { text: '插入', type: 'primary', onclick: () => {
                        const select = document.getElementById('template-select');
                        const templateId = select.value;
                        if (!templateId) {
                            Dom.toast('请选择模板', 'error');
                            return;
                        }
                        AjaxLoader.api('emr/template-get?id=' + templateId, { silent: true })
                            .then(res => {
                                callback(res.data);
                                // 累加使用次数
                                AjaxLoader.api('emr/template-use?id=' + templateId, { method: 'POST', silent: true });
                            });
                    }},
                    { text: '取消', type: 'default' }
                ]);
            })
            .catch(() => {
                callback(null);
            });
    }
};
