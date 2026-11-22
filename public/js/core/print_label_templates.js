// public/js/core/print_label_templates.js - 医疗非标标签模板（腕带/检验/药品执行贴）
(function () {
    'use strict';
    const PL = (window.PrintLabels = {});
    const esc = function (value) { return Format.escape(String(value == null ? '' : value)); };

    PL.open = function (template, opts) {
        if (!template || !template.html) { Dom.toast('暂无可打印的标签内容', 'info'); return null; }
        return PrintPreview.openLabels(template.title || '标签', template.html, template.paper,
            Object.assign({ css: template.css || '', previewTitle: template.previewTitle }, opts || {}));
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

    PL.labBarcode = function (report) {
        const data = PrintPreview.patientInfo(report || {}, '检验条码');
        const reportNo = String(data.report_no || '');
        if (!reportNo) return null;
        const ward = String(data.ward || '-');
        const examName = String(data.exam_name || '检验');
        const lengthClass = function (value) { return value.length > 16 ? ' is-xlong' : (value.length > 10 ? ' is-long' : ''); };
        const html = '<div class="lab-tube-label"><div class="lb-patient"><b class="lb-name">' + esc(data.name) + '</b>'
            + '<span>' + esc(data.gender) + ' ' + esc(data.age) + '</span><b>' + esc(data.bed || '-') + '床</b></div>'
            + '<div class="lb-ward' + lengthClass(ward) + '" title="' + esc(ward) + '">病区：' + esc(ward) + '</div>'
            + '<div class="lb-barcode">' + PrintBarcode.svg(reportNo, {
                height: 38, fontSize: 11, quietModules: 8, className: 'lb-barcode-svg'
            }) + '</div><div class="lb-exam' + lengthClass(examName) + '" title="' + esc(examName) + '">' + esc(examName) + '</div>'
            + '<div class="lb-meta"><span>' + esc(data.specimen_type || '标本') + '</span>'
            + '<span>' + esc(String(data.created_at || '').slice(5, 16)) + '</span></div></div>';
        const css = `
.lab-tube-label { width:100%; height:100%; padding:1mm 1.5mm; display:grid; grid-template-rows:3.5mm 3.5mm 14mm 3.5mm 3.5mm; font-family:"PingFang SC","Microsoft YaHei",sans-serif; color:#000; }
.lb-patient { min-width:0; display:grid; grid-template-columns:minmax(0,1fr) auto auto; align-items:center; gap:1.2mm; font-size:6.8pt; line-height:1; white-space:nowrap; }
.lb-name { min-width:0; overflow:hidden; text-overflow:ellipsis; font-size:8.5pt; }
.lb-ward, .lb-exam { display:block; width:100%; min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; line-height:3.5mm; }
.lb-ward { font-size:6.5pt; }
.lb-exam { font-size:6.8pt; font-weight:700; }
.lb-ward.is-long, .lb-exam.is-long { font-size:5.9pt; }
.lb-ward.is-xlong, .lb-exam.is-xlong { font-size:5.3pt; }
.lb-barcode { min-height:0; display:flex; align-items:center; justify-content:center; overflow:hidden; }
.lb-barcode-svg { display:block; width:100%; height:13.5mm; }
.lb-meta { min-width:0; display:flex; align-items:flex-end; justify-content:space-between; gap:1.5mm; overflow:hidden; font-size:6.2pt; line-height:1; white-space:nowrap; }
.lb-meta span { min-width:0; overflow:hidden; text-overflow:ellipsis; }
.lb-meta span:last-child { flex:0 0 auto; }
`;
        return { title: '检验条码打印', previewTitle: '检验条码打印', paper: 'LAB_LABEL', html: html, css: css };
    };

    PL.medicationExecution = function (order) {
        const data = PrintPreview.patientInfo(order || {}, '药品医嘱执行贴');
        const admissionNo = String(data.admission_no || '');
        if (!admissionNo || !data.content) return null;
        const brand = (typeof HospitalBranding !== 'undefined' && HospitalBranding.get)
            ? HospitalBranding.get() : { hospital_name: '' };
        const usage = [data.usage_name, data.freq_code, data.freq_detail, data.usage_rate].filter(Boolean).join(' · ');
        const dose = data.dosage ? String(data.dosage) + (data.dosage_unit ? '/' + data.dosage_unit : '') : '';
        const birthDate = String(data.birth_date || '').slice(0, 10) || '-';
        const location = [data.department ? '科室 ' + data.department : '', data.ward ? '病区 ' + data.ward : ''].filter(Boolean).join(' · ') || '-';
        const html = '<div class="med-exec-label"><div class="mel-head"><span>' + esc(brand.hospital_name || '') + '</span>'
            + '<strong>' + esc(data.bed || '-') + '床</strong></div>'
            + '<div class="mel-patient"><div class="mel-patient-main"><b>' + esc(data.name) + '</b><span>' + esc(data.gender) + '</span>'
            + '<span>' + esc(data.age) + '</span><span>出生 ' + esc(birthDate) + '</span></div>'
            + '<div class="mel-patient-sub" title="' + esc(location) + '">' + esc(location) + '</div></div><div class="mel-barcode">'
            + PrintBarcode.svg(admissionNo, { height: 32, fontSize: 10, quietModules: 8, className: 'mel-barcode-svg' })
            + '</div><div class="mel-drug"><b>' + esc(data.content) + '</b>'
            + '<div>' + (dose ? '剂量：' + esc(dose) : '') + (usage ? '　' + esc(usage) : '') + '</div>'
            + (data.notes ? '<div class="mel-note">备注：' + esc(data.notes) + '</div>' : '') + '</div>'
            + '<div class="mel-foot"><div class="mel-staff"><div><span>开单</span><b>' + esc(data.doctor_name || '-') + '</b></div>'
            + '<div><span>核对</span><b>' + esc(data.verified_name || '-') + '</b></div>'
            + '<div><span>执行</span><b>' + esc(data.executed_name || '　') + '</b></div>'
            + '<div class="mel-order-time">开单时间 ' + esc(String(data.created_at || data.start_date || '').slice(0, 16)) + '</div></div>'
            + '<div class="mel-stamp">执行护士<br>盖章</div></div></div>';
        const css = `
.med-exec-label { width:100%; height:100%; padding:2mm; display:grid; grid-template-rows:5mm 9mm 10mm minmax(0,1fr) 14mm; font-family:"PingFang SC","Microsoft YaHei",sans-serif; color:#000; }
.mel-head { min-width:0; display:grid; grid-template-columns:minmax(0,1fr) auto; align-items:center; gap:1mm; border-bottom:.6pt solid #000; font-size:7.2pt; white-space:nowrap; }
.mel-head span { min-width:0; overflow:hidden; text-overflow:ellipsis; font-weight:700; }
.mel-head strong { justify-self:end; font-size:10pt; }
.mel-patient { min-width:0; display:grid; grid-template-rows:1fr 1fr; align-items:center; font-size:6.6pt; line-height:1; white-space:nowrap; }
.mel-patient-main { min-width:0; display:flex; align-items:center; justify-content:space-between; gap:1mm; }
.mel-patient-main b { font-size:9pt; }
.mel-patient-sub { display:block; width:100%; min-width:0; overflow:hidden; text-overflow:ellipsis; }
.mel-barcode { min-height:0; display:flex; align-items:center; justify-content:center; overflow:hidden; }
.mel-barcode-svg { display:block; width:100%; height:10mm; }
.mel-drug { min-height:0; padding:1.2mm 1mm; border:.7pt solid #000; overflow:hidden; font-size:7.3pt; line-height:1.35; overflow-wrap:anywhere; }
.mel-drug > b { display:-webkit-box; overflow:hidden; -webkit-box-orient:vertical; -webkit-line-clamp:2; font-size:10pt; line-height:1.3; }
.mel-note { white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.mel-foot { padding-top:1mm; display:grid; grid-template-columns:minmax(0,1fr) 16mm; gap:1.5mm; font-size:6.5pt; line-height:1.2; }
.mel-staff { min-width:0; display:grid; grid-template-columns:1fr 1fr; gap:.5mm 1.5mm; }
.mel-staff > div { min-width:0; display:flex; gap:1mm; white-space:nowrap; }
.mel-staff span { color:#555; }
.mel-staff b { min-width:0; overflow:hidden; text-overflow:ellipsis; font-weight:600; }
.mel-order-time { grid-column:1 / span 2; }
.mel-stamp { display:flex; align-items:center; justify-content:center; text-align:center; border:.7pt dashed #000; font-size:7pt; color:#555; }
`;
        return { title: '医嘱执行单打印', previewTitle: '医嘱执行单打印', paper: 'MED_LABEL', html: html, css: css };
    };
})();
