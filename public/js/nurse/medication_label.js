// public/js/nurse/medication_label.js - 药品医嘱执行贴数据加载与统一预览
(function () {
    'use strict';
    const ML = (window.MedicationLabel = {});

    ML.preview = function (orderId, opts) {
        const options = opts || {};
        return AjaxLoader.api('nurse/execution-label?order_id=' + orderId, { silent: true }).then(function (response) {
            const order = response.data || {};
            if (options.expectedPatientId != null && String(order.patient_id) !== String(options.expectedPatientId)) {
                Dom.toast('患者已切换，已取消药品执行贴预览', 'info');
                return null;
            }
            const template = PrintLabels.medicationExecution(order);
            if (!template) { Dom.toast('医嘱缺少患者住院号或药品信息', 'error'); return null; }
            return PrintLabels.open(template);
        }).catch(function () { Dom.toast('药品执行贴数据加载失败', 'error'); return null; });
    };
})();
