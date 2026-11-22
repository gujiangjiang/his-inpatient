// public/js/core/print_label_templates.js - 医疗非标标签模板（腕带/检验/药品执行贴）
(function () {
    'use strict';
    const PL = (window.PrintLabels = {});
    const esc = function (value) { return Format.escape(String(value == null ? '' : value)); };

    PL.open = function (template, opts) {
        const labels = template && template.pages && template.pages.length ? template.pages : (template && template.html);
        if (!template || !labels) { Dom.toast('暂无可打印的标签内容', 'info'); return null; }
        return PrintPreview.openLabels(template.title || '标签', labels, template.paper,
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
        const department = String(data.department || '-');
        const ward = String(data.ward || '-');
        const examName = String(data.exam_name || '检验');
        const labCategory = String(data.lab_category || '-');
        const orderTime = String(data.created_at || '').replace('T', ' ').slice(0, 19) || '-';
        const lengthClass = function (value) { return value.length > 16 ? ' is-xlong' : (value.length > 10 ? ' is-long' : ''); };
        const html = '<div class="lab-tube-label"><div class="lb-patient"><b class="lb-name">' + esc(data.name) + '</b>'
            + '<span class="lb-gender">' + esc(data.gender) + '</span><span class="lb-age">' + esc(data.age) + '</span>'
            + '<b class="lb-bed">' + esc(data.bed || '-') + '床</b></div>'
            + '<div class="lb-location-row"><span class="lb-dept' + lengthClass(department) + '" title="' + esc(department) + '">科室：' + esc(department) + '</span>'
            + '<span class="lb-ward' + lengthClass(ward) + '" title="' + esc(ward) + '">病区：' + esc(ward) + '</span></div>'
            + '<div class="lb-barcode">' + PrintBarcode.svg(reportNo, {
                height: 38, fontSize: 11, quietModules: 8, className: 'lb-barcode-svg'
            }) + '</div><div class="lb-exam-row"><b class="lb-exam' + lengthClass(examName) + '" title="' + esc(examName) + '">' + esc(examName) + '</b>'
            + '<span class="lb-category' + lengthClass(labCategory) + '" title="' + esc(labCategory) + '">' + esc(labCategory) + '</span></div>'
            + '<div class="lb-order-meta"><span class="lb-doctor">开单：' + esc(data.creator_name || '-') + '</span>'
            + '<span class="lb-time">' + esc(orderTime) + '</span></div></div>';
        const css = `
.lab-tube-label { width:100%; height:100%; padding:1mm 1.5mm; display:grid; grid-template-rows:3.5mm 3.5mm 14mm 3.5mm 3.5mm; font-family:"PingFang SC","Microsoft YaHei",sans-serif; color:#000; }
.lb-patient { min-width:0; display:grid; grid-template-columns:minmax(0,1fr) auto auto minmax(0,1fr); align-items:center; gap:1.2mm; font-size:6.8pt; line-height:1; white-space:nowrap; }
.lb-name { min-width:0; overflow:hidden; text-overflow:ellipsis; font-size:8.5pt; }
.lb-gender, .lb-age { justify-self:center; }
.lb-bed { min-width:0; justify-self:end; overflow:hidden; text-overflow:ellipsis; }
.lb-location-row { min-width:0; display:grid; grid-template-columns:minmax(0,1fr) minmax(0,1fr); align-items:center; gap:1.5mm; white-space:nowrap; }
.lb-dept, .lb-ward, .lb-exam, .lb-category { display:block; width:100%; min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; line-height:3.5mm; }
.lb-dept, .lb-ward { font-size:6.5pt; }
.lb-ward { text-align:right; }
.lb-exam-row { min-width:0; display:grid; grid-template-columns:minmax(0,1.5fr) minmax(0,.8fr); align-items:center; gap:1.5mm; }
.lb-exam { font-size:6.8pt; font-weight:700; }
.lb-category { text-align:right; font-size:6.5pt; }
.lb-dept.is-long, .lb-ward.is-long, .lb-exam.is-long, .lb-category.is-long { font-size:5.9pt; }
.lb-dept.is-xlong, .lb-ward.is-xlong, .lb-exam.is-xlong, .lb-category.is-xlong { font-size:5.3pt; }
.lb-barcode { min-height:0; display:flex; align-items:center; justify-content:center; overflow:hidden; }
.lb-barcode-svg { display:block; width:100%; height:13.5mm; }
.lb-order-meta { min-width:0; display:grid; grid-template-columns:minmax(0,1fr) auto; align-items:end; gap:1.5mm; overflow:hidden; font-size:5.8pt; line-height:1; white-space:nowrap; }
.lb-doctor { min-width:0; overflow:hidden; text-overflow:ellipsis; }
.lb-time { justify-self:end; font-variant-numeric:tabular-nums; }
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
        const department = String(data.department || '-');
        const ward = String(data.ward || '-');
        const members = Array.isArray(data.group_items) && data.group_items.length ? data.group_items : [data];
        const chunks = [];
        for (let i = 0; i < members.length; i += 4) chunks.push(members.slice(i, i + 4));
        const itemDose = function (item) {
            return item.dosage ? String(item.dosage) + (item.dosage_unit ? '/' + item.dosage_unit : '') : '';
        };
        const itemClass = function (item) {
            const length = String(item.content || '').length;
            return length > 18 ? ' is-xlong' : (length > 12 ? ' is-long' : '');
        };
        const drugHtml = function (chunk, chunkIndex) {
            if (members.length === 1) {
                return '<div class="mel-drug"><b>' + esc(data.content) + '</b>'
                    + '<div>' + (dose ? '剂量：' + esc(dose) : '') + (usage ? '　' + esc(usage) : '') + '</div>'
                    + (data.notes ? '<div class="mel-note">备注：' + esc(data.notes) + '</div>' : '') + '</div>';
            }
            const offset = chunkIndex * 4;
            const rows = chunk.map(function (item, index) {
                return '<div class="mel-drug-item' + itemClass(item) + '"><span>' + (offset + index + 1) + '</span>'
                    + '<b title="' + esc(item.content || '') + '">' + esc(item.content || '-') + '</b>'
                    + '<em>' + esc(itemDose(item)) + '</em></div>';
            }).join('');
            return '<div class="mel-drug is-group"><div class="mel-drug-list">' + rows + '</div>'
                + '<div class="mel-group-usage">组合 ' + members.length + ' 项' + (usage ? ' · ' + esc(usage) : '') + '</div></div>';
        };
        const pageHtml = function (chunk, index) {
            const pageNo = chunks.length > 1 ? ' · ' + (index + 1) + '/' + chunks.length : '';
            return '<div class="med-exec-label"><div class="mel-head"><span>' + esc(brand.hospital_name || '') + '</span>'
                + '<strong>' + esc(data.bed || '-') + '床' + pageNo + '</strong></div>'
                + '<div class="mel-patient"><div class="mel-patient-main"><b>' + esc(data.name) + '</b><span>' + esc(data.gender) + '</span>'
                + '<span>' + esc(data.age) + '</span><span>出生 ' + esc(birthDate) + '</span></div>'
                + '<div class="mel-patient-sub"><span title="' + esc(department) + '">科室 ' + esc(department) + '</span>'
                + '<span title="' + esc(ward) + '">病区 ' + esc(ward) + '</span></div></div>'
                + drugHtml(chunk, index) + '<div class="mel-barcode">'
                + PrintBarcode.svg(admissionNo, { height: 32, fontSize: 10, quietModules: 8, className: 'mel-barcode-svg' }) + '</div>'
                + '<div class="mel-foot"><div class="mel-staff"><div><span>开单</span><b>' + esc(data.doctor_name || '-') + '</b></div>'
                + '<div><span>核对</span><b>' + esc(data.verified_name || '-') + '</b></div>'
                + '<div class="mel-order-time">开单时间 ' + esc(String(data.created_at || data.start_date || '').slice(0, 16)) + '</div></div>'
                + '<div class="mel-stamp">执行护士盖章</div></div></div>';
        };
        const pages = chunks.map(pageHtml);
        const css = `
.med-exec-label { width:100%; height:100%; padding:2mm; display:grid; grid-template-rows:5mm 9mm auto 10mm 9mm; align-content:space-between; row-gap:1mm; font-family:"PingFang SC","Microsoft YaHei",sans-serif; color:#000; }
.mel-head { min-width:0; display:grid; grid-template-columns:minmax(0,1fr) auto; align-items:center; gap:1mm; border-bottom:.6pt solid #000; font-size:7.2pt; white-space:nowrap; }
.mel-head span { min-width:0; overflow:hidden; text-overflow:ellipsis; font-weight:700; }
.mel-head strong { justify-self:end; font-size:10pt; }
.mel-patient { min-width:0; display:grid; grid-template-rows:1fr 1fr; align-items:center; font-size:6.6pt; line-height:1; white-space:nowrap; }
.mel-patient-main { min-width:0; display:flex; align-items:center; justify-content:space-between; gap:1mm; }
.mel-patient-main b { font-size:9pt; }
.mel-patient-sub { width:100%; min-width:0; display:grid; grid-template-columns:minmax(0,1fr) minmax(0,1fr); gap:1.5mm; }
.mel-patient-sub span { min-width:0; overflow:hidden; text-overflow:ellipsis; }
.mel-patient-sub span:last-child { text-align:right; }
.mel-barcode { min-height:0; display:flex; align-items:center; justify-content:center; overflow:hidden; }
.mel-barcode-svg { display:block; width:100%; height:10mm; }
.mel-drug { min-height:0; max-height:19mm; align-self:start; padding:1.2mm 1mm; border:.7pt solid #000; overflow:hidden; font-size:7.5pt; line-height:1.35; overflow-wrap:anywhere; }
.mel-drug > b { display:-webkit-box; overflow:hidden; -webkit-box-orient:vertical; -webkit-line-clamp:3; font-size:10pt; line-height:1.3; }
.mel-note { white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.mel-drug.is-group { width:100%; padding:.8mm 1mm .6mm; }
.mel-drug-list { display:grid; gap:.2mm; }
.mel-drug-item { min-width:0; display:grid; grid-template-columns:3mm minmax(0,1fr) auto; align-items:baseline; column-gap:.8mm; font-size:6.8pt; line-height:1.2; white-space:nowrap; }
.mel-drug-item > span { color:#666; }
.mel-drug-item > b { min-width:0; overflow:hidden; text-overflow:ellipsis; }
.mel-drug-item > em { max-width:13mm; overflow:hidden; text-overflow:ellipsis; font-size:6.2pt; font-style:normal; }
.mel-drug-item.is-long { font-size:6.1pt; }
.mel-drug-item.is-xlong { font-size:5.5pt; }
.mel-group-usage { margin-top:.5mm; padding-top:.4mm; border-top:.4pt solid #999; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; font-size:6.2pt; }
.mel-foot { padding-top:.7mm; display:grid; grid-template-columns:minmax(0,1fr) 21mm; align-items:center; gap:1.5mm; font-size:6.5pt; line-height:1.2; }
.mel-staff { min-width:0; display:grid; grid-template-columns:1fr 1fr; gap:.5mm 1.5mm; }
.mel-staff > div { min-width:0; display:flex; gap:1mm; white-space:nowrap; }
.mel-staff span { color:#555; }
.mel-staff b { min-width:0; overflow:hidden; text-overflow:ellipsis; font-weight:600; }
.mel-order-time { grid-column:1 / span 2; }
.mel-stamp { width:21mm; height:7mm; justify-self:end; display:flex; align-items:center; justify-content:center; text-align:center; border:.7pt dashed #888; font-size:6.5pt; color:#b8b8b8; white-space:nowrap; }
`;
        return { title: '医嘱执行单打印', previewTitle: '医嘱执行单打印', paper: 'MED_LABEL', html: pages[0], pages: pages, css: css };
    };
})();
