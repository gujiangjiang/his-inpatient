// public/js/core/print_label_templates.js - 医疗非标标签模板（腕带/检验/药品执行贴）
(function () {
    'use strict';
    const PL = (window.PrintLabels = {});
    const esc = function (value) { return Format.escape(String(value == null ? '' : value)); };

    PL.open = function (template, opts) {
        if (!template || !template.html) { Dom.toast('暂无可打印的标签内容', 'info'); return null; }
        return PrintPreview.openLabels(template.title || '标签', template.html, template.paper,
            Object.assign({ css: template.css || '' }, opts || {}));
    };

    PL.wristband = function (patient) {
        const data = PrintPreview.patientInfo(patient || {}, '患者腕带');
        const brand = (typeof HospitalBranding !== 'undefined' && HospitalBranding.get)
            ? HospitalBranding.get() : { hospital_name: '' };
        const admissionNo = data.admission_no || '';
        if (!admissionNo) return null;
        const allergy = data.allergy_history && data.allergy_history !== '无'
            ? '<span class="wb-allergy">过敏：' + esc(data.allergy_history) + '</span>' : '<span>过敏：无</span>';
        const html = '<div class="wristband-label">'
            + '<div class="wb-line wb-primary"><span class="wb-hospital">' + esc(brand.hospital_name || '') + '</span>'
            + '<span class="wb-name">' + esc(data.name) + '</span><span>' + esc(data.gender) + '</span>'
            + '<span>' + esc(data.age) + '</span><span>床号：<b>' + esc(data.bed || '-') + '</b></span></div>'
            + '<div class="wb-line"><span>住院号：<b>' + esc(admissionNo) + '</b></span>'
            + '<span>科室：' + esc(data.department || '-') + '</span><span>病区：' + esc(data.ward || '-') + '</span>'
            + allergy + '</div><div class="wb-barcode">'
            + PrintBarcode.svg(admissionNo, { height: 42, fontSize: 14, className: 'wb-barcode-svg' })
            + '</div></div>';
        const css = `
.wristband-label { width:100%; height:100%; padding:2mm 4mm; display:grid; grid-template-columns:minmax(0,1fr) 74mm; grid-template-rows:1fr 1fr; column-gap:4mm; align-items:center; font-family:"PingFang SC","Microsoft YaHei",sans-serif; font-size:9pt; color:#000; }
.wb-line { min-width:0; display:flex; align-items:center; gap:0 4mm; white-space:nowrap; overflow:hidden; line-height:1.25; }
.wb-line > span { flex:0 0 auto; }
.wb-primary { font-size:10pt; }
.wb-hospital { max-width:48mm; overflow:hidden; text-overflow:ellipsis; font-weight:600; }
.wb-name { font-size:12pt; font-weight:800; }
.wb-allergy { color:#b42318; font-weight:700; max-width:45mm; overflow:hidden; text-overflow:ellipsis; }
.wb-barcode { grid-column:2; grid-row:1 / span 2; width:100%; height:100%; display:flex; align-items:center; justify-content:center; }
.wb-barcode-svg { display:block; width:100%; height:19mm; }
`;
        return { title: '患者腕带 - ' + data.name, paper: 'WRISTBAND', html: html, css: css };
    };
})();
