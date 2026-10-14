// public/js/case_front_page/home_page1.js - 住院病案首页(一) 打印版式
// 底层全表格: 信息行为隐藏表格线的定宽列表格 (内容长短不串位), 诊断表显示表格线
Object.assign(CaseHome, {
    diagTableHtml() {
        const diags = this.data.discharge_diags || [];
        const total = Math.max(12, diags.length);
        let rows = '';
        for (let i = 0; i < total; i++) {
            const d = diags[i];
            if (d && d.name) {
                const label = i === 0 ? '主要诊断' : '其他诊断';
                rows += `<tr><td>${label}　<span class="cf-v">${Format.escape(d.name)}</span></td>
                    <td>${this.fn2(d.code)}</td><td>${this.fn2(d.in_status)}</td><td>${this.fn2(d.outcome)}</td></tr>`;
            } else {
                rows += '<tr><td class="cf-tc">—</td><td class="cf-tc">—</td><td class="cf-tc">—</td><td class="cf-tc">—</td></tr>';
            }
        }
        return `<table class="cf-diag">
            <thead><tr><th style="width:46%">出　院　诊　断</th><th style="width:21%">疾病编码</th>
            <th style="width:16.5%">入院病情</th><th style="width:16.5%">治疗转归</th></tr></thead>
            <tbody>${rows}</tbody></table>`;
    },

    fn2(x) {
        return (x === '' || x === undefined || x === null) ? '—' : Format.escape(String(x));
    },

    buildPage1() {
        return `
<div class="cf-page">
    <div class="cf-head">
        <div class="cf-head-top">
            <span class="cf-org">医疗机构 <span class="cf-fill-line">${this.h('hospital_name') || '—'}</span></span>
            <span class="cf-orgcode">（组织机构代码：<span class="cf-fill-line">${this.h('hospital_code') || '—'}</span>）</span>
        </div>
        <div class="cf-title-row">
            <span class="cf-pay">医疗付费方式：${this.bx('pay_type')}</span>
            <div class="cf-title">住院病案首页(一)</div>
        </div>
        <div class="cf-head-id">
            <span>健康卡号：<span class="cf-fill-line">${this.h('health_card') || '—'}</span></span>
            <span>ID：<span class="cf-fill-line">${this.h('mr_no') || '—'}</span></span>
            <span>第 <span class="cf-num">${this.fn('admit_times', '')}</span> 次住院</span>
            <span>住院号：<span class="cf-fill-line">${Format.escape(this.admissionNo || '')}</span></span>
        </div>
    </div>
    <table class="cf1"><tbody>
        <tr class="cf-line"><td>${this.rt([
            [18, `姓名 <span class="cf-v">${this.h('name')}</span>`],
            [30, `性别 ${this.bx('gender')} <span class="cf-dict">0.未知的性别 1.男 2.女 9.未说明的性别</span>`],
            [52, `出生日期 <span class="cf-v">${this.h('birth_date')}</span>`],
        ])}</td></tr>
        <tr class="cf-line"><td>${this.rt([
            [19, `年龄 <span class="cf-v">${this.vd('age_y')}</span> 岁`],
            [19, `国籍 <span class="cf-v">${this.fn('nationality', '')}</span>`],
            [26, `（年龄不足1周岁的）年龄 <span class="cf-v">${this.vd('age_m')}</span> 月`],
            [36, `（新生儿≤28天的）年龄 <span class="cf-v">${this.vd('age_d')}</span> 天`],
        ])}</td></tr>
        <tr class="cf-line"><td>${this.rt([
            [33, `新生儿出生体重（一孩 <span class="cf-v">${this.vd('baby1_weight')}</span> 克`],
            [22, `二孩 <span class="cf-v">${this.vd('baby2_weight')}</span> 克）`],
            [45, `新生儿入院体重 <span class="cf-v">${this.vd('baby_admit_weight')}</span> 克`],
        ])}</td></tr>
        <tr class="cf-line"><td>${this.rt([
            [63, `身份证件类型 ${this.bx('id_type')} <span class="cf-dict">0.居民身份证 1.护照 2.港澳台居民居住证/通行证 4.旅行证件 9.其他</span>`],
            [37, `身份证件号码 <span class="cf-v">${this.h('id_card')}</span>`],
        ])}</td></tr>
        <tr class="cf-line"><td>身份证件不详的具体原因 ${this.bx('id_unknown_reason')} <span class="cf-dict">1.三无人员 2.新生儿未办理 3.无完全民事行为能力 4.意识障碍 9.其他</span>　<span class="cf-fill-line" style="min-width:30mm"></span></td></tr>
        <tr class="cf-line"><td>${this.rt([
            [46, `出生地 <span class="cf-v">${this.h('birth_prov')}</span> 省（区、市） <span class="cf-v">${this.h('birth_city')}</span> 市 <span class="cf-v">${this.h('birth_county')}</span> 县`],
            [30, `籍贯 <span class="cf-v">${this.h('native_prov')}</span> 省（区、市） <span class="cf-v">${this.h('native_city')}</span> 市`],
            [24, `民族 <span class="cf-v">${this.h('nation')}</span>`],
        ])}</td></tr>
        <tr class="cf-line"><td>${this.rt([
            [29, `职业 <span class="cf-v">${this.h('occupation')}</span>`],
            [71, `婚姻 ${this.bx('marriage')} <span class="cf-dict">1.未婚 2.已婚 3.丧偶 4.离婚 9.其他</span>`],
        ])}</td></tr>
        <tr class="cf-line"><td>${this.rt([
            [63, `现住址 <span class="cf-v">${this.h('addr_prov')}</span> 省（区、市） <span class="cf-v">${this.h('addr_city')}</span> 市 <span class="cf-v">${this.h('addr_county')}</span> 县 <span class="cf-v">${this.h('addr_detail')}</span>`],
            [20, `电话 <span class="cf-v">${this.h('phone')}</span>`],
            [17, `邮编 <span class="cf-v">${this.h('zip')}</span>`],
        ])}</td></tr>
        <tr class="cf-line"><td>${this.rt([
            [78, `户口地址 <span class="cf-v">${this.h('hukou_prov')}</span> 省（区、市） <span class="cf-v">${this.h('hukou_city')}</span> 市 <span class="cf-v">${this.h('hukou_county')}</span> 县 <span class="cf-v">${this.h('hukou_detail')}</span>`],
            [22, `邮编 <span class="cf-v">${this.h('hukou_zip')}</span>`],
        ])}</td></tr>
        <tr class="cf-line"><td>${this.rt([
            [57, `工作单位及地址 <span class="cf-v">${this.fn('work_unit', '—')}</span>`],
            [25, `电话 <span class="cf-v">${this.h('work_phone')}</span>`],
            [18, `邮编 <span class="cf-v">${this.h('work_zip')}</span>`],
        ])}</td></tr>
        <tr class="cf-line"><td>${this.rt([
            [21, `联系人姓名 <span class="cf-v">${this.h('contact_name')}</span>`],
            [22, `关系 <span class="cf-v">${this.h('contact_rel')}</span>`],
            [27, `地址 <span class="cf-v">${this.h('contact_addr') || '—'}</span>`],
            [30, `电话 <span class="cf-v">${this.h('contact_phone')}</span>`],
        ])}</td></tr>
        <tr class="cf-line cf-topline"><td>入院途径 ${this.bx('admit_route')} <span class="cf-dict">1.急诊 2.门诊 3.其他医疗机构转入 9.其他</span></td></tr>
        <tr class="cf-line"><td>${this.rt([
            [31, `入院时间 <span class="cf-v">${this.fmtCn('admit_time')}</span>`],
            [21, `入院科别 <span class="cf-v">${this.fn('admit_dept', '—')}</span>`],
            [23, `病房 <span class="cf-v">${this.fn('admit_ward', '—')}</span>`],
            [25, `转科科别 <span class="cf-v">${this.fn('transfer_dept', '—')}</span>`],
        ])}</td></tr>
        <tr class="cf-line"><td>${this.rt([
            [31, `出院时间 <span class="cf-v">${this.fmtCn('discharge_time')}</span>`],
            [21, `出院科别 <span class="cf-v">${this.fn('discharge_dept', '—')}</span>`],
            [23, `病房 <span class="cf-v">${this.fn('discharge_ward', '—')}</span>`],
            [25, `实际住院 <span class="cf-v">${this.vd('stay_days')}</span> 天`],
        ])}</td></tr>
        <tr class="cf-line"><td>${this.rt([
            [52, `门(急)诊诊断(西医诊断) <span class="cf-v">${this.fn('diagnosis_emergency', '—')}</span>`],
            [22, `疾病编码 <span class="cf-v">${this.fn('diagnosis_emergency_code', '—')}</span>`],
            [26, `确诊日期 <span class="cf-v">${this.vd('diagnose_date')}</span>`],
        ])}</td></tr>
        <tr class="cf-diagrow"><td>${this.diagTableHtml()}</td></tr>
        <tr class="cf-line"><td class="cf-note">${this.rt([
            [36, `入院病情：1.有 2.临床未确定 3.情况不明 4.无`],
            [64, `治疗转归：1.治愈 2.好转 3.未愈 5.死亡 9.其他`],
        ])}</td></tr>
        <tr class="cf-line"><td>${this.rt([
            [65, `损伤、中毒的外部原因 <span class="cf-v">${this.fn('injury_reason', '—')}</span>`],
            [35, `疾病编码 <span class="cf-v">${this.fn('injury_code', '—')}</span>`],
        ])}</td></tr>
        <tr class="cf-line"><td>${this.rt([
            [65, `病理诊断：<span class="cf-v">${this.fn('pathology_diagnosis', '—')}</span>`],
            [35, `疾病编码 <span class="cf-v">${this.fn('pathology_code', '—')}</span><br>病理号 <span class="cf-v">${this.fn('pathology_no', '—')}</span>`],
        ])}</td></tr>
        <tr class="cf-line cf-topline"><td>${this.rt([
            [62, `药物过敏 ${this.bx('allergy')} <span class="cf-dict">1.无 2.有，过敏药物：</span><span class="cf-v">${this.fn('allergy_drugs', '—')}</span>`],
            [38, `死亡患者尸检 ${this.bx('autopsy')} <span class="cf-dict">1.是 2.否</span>`],
        ])}</td></tr>
        <tr class="cf-line"><td>${this.rt([
            [50, `血型 ${this.bx('blood_type')} <span class="cf-dict">1.A 2.B 3.O 4.AB 5.不详 6.未查</span>`],
            [50, `Rh ${this.bx('rh')} <span class="cf-dict">1.阴 2.阳 3.不详 4.未查</span>`],
        ])}</td></tr>
        <tr class="cf-line cf-topline"><td>${this.rt([
            [30, `科主任 <span class="cf-v">${this.fn('chief_physician', '—')}</span>`],
            [23, `主任（副主任）医师 <span class="cf-v">${this.fn('director_physician', '—')}</span>`],
            [21, `主治医师 <span class="cf-v">${this.fn('attending_physician', '—')}</span>`],
            [26, `住院医师 <span class="cf-v">${this.fn('resident_physician', '—')}</span>`],
        ])}</td></tr>
        <tr class="cf-line"><td>${this.rt([
            [30, `责任护士 <span class="cf-v">${this.fn('charge_nurse', '—')}</span>`],
            [23, `进修医师 <span class="cf-v">${this.fn('trainee', '—')}</span>`],
            [21, `实习医师 <span class="cf-v">${this.fn('intern', '—')}</span>`],
            [26, `编码员 <span class="cf-v">${this.fn('coder', '—')}</span>`],
        ])}</td></tr>
        <tr class="cf-line"><td>${this.rt([
            [27, `病案质量 ${this.bx('case_quality')} <span class="cf-dict">1.甲 2.乙 3.丙</span>`],
            [22, `质控医师 <span class="cf-v">${this.fn('qc_physician', '—')}</span>`],
            [23, `质控护士 <span class="cf-v">${this.fn('qc_nurse', '—')}</span>`],
            [28, `质控日期 <span class="cf-v">${this.fn('qc_date', '—')}</span>`],
        ])}</td></tr>
    </tbody></table>
</div>`;
    },
});
