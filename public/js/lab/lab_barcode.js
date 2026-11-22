// public/js/lab/lab_barcode.js - 检验试管条码预览、打印留痕与状态展示
(function () {
    'use strict';
    const LB = (window.LabBarcode = {});

    LB.printCount = function (report) {
        return Math.max(0, parseInt(report && report.barcode_print_count, 10) || 0);
    };

    LB.statusHtml = function (report) {
        const count = LB.printCount(report);
        if (!count) return '<span class="badge badge-warning">未打印</span>';
        const detail = [report.barcode_printed_name, report.barcode_printed_at].filter(Boolean).join(' · ');
        return '<span class="badge badge-success" title="' + Format.escape(detail) + '">已打印 ' + count + ' 次</span>';
    };

    LB.buttonText = function (report) {
        return LB.printCount(report) ? '补打条码' : '打印条码';
    };

    LB.preview = function (id, opts) {
        const options = opts || {};
        return AjaxLoader.api('lab/detail?id=' + id, { silent: true }).then(function (response) {
            const report = response.data || {};
            if (options.expectedPatientId != null && String(report.patient_id) !== String(options.expectedPatientId)) {
                Dom.toast('患者已切换，已取消检验条码预览', 'info');
                return null;
            }
            const template = PrintLabels.labBarcode(report);
            if (!template) { Dom.toast('检验单缺少申请单号，无法生成条码', 'error'); return null; }
            return PrintLabels.open(template, {
                onPrint: function () {
                    return AjaxLoader.api('lab/barcode-print', { method: 'POST', data: { id: id } }).then(function (result) {
                        if (typeof options.onPrinted === 'function') options.onPrinted(result.data || {});
                        return result;
                    });
                }
            });
        }).catch(function () { Dom.toast('检验条码信息加载失败', 'error'); return null; });
    };
})();
