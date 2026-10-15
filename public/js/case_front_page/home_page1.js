// public/js/case_front_page/home_page1.js - 住院病案首页(一) 打印版式
// 底层全表格: 信息行为隐藏表格线的定宽列表格 (内容长短不串位), 值位固定下划线, 诊断表显示表格线
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
            [18, `姓名 ${this.ub('name', 20)}`],
            [30, `性别 ${this.bx('gender')} <span class="cf-dict">0.未知的性别 1.男 2.女 9.未说明的性别</span>`],
            [52, `出生日期 ${this.ub('birth_date', 30)}`],
        ])}</td></tr>
        <tr class="cf-line"><td>${this.rt([
            [19, `年龄 ${this.ub('age_y', 10)} 岁`],
            [19, `国籍 ${this.ub('nationality', 18)}`],
            [27, `（年龄不足1周岁的）年龄 ${this.ub('age_m', 8)} 月`],
            [35, `（新生儿≤28天的）年龄 ${this.ub('age_d', 8)} 天`],
        ])}</td></tr>
        <tr class="cf-line"><td>${this.rt([
            [33, `新生儿出生体重（一孩 ${this.ub('baby1_weight', 12)} 克`],
            [22, `二孩 ${this.ub('baby2_weight', 12)} 克）`],
            [45, `新生儿入院体重 ${this.ub('baby_admit_weight', 12)} 克`],
        ])}</td></tr>
        <tr class="cf-line"><td>${this.rt([
            [63, `身份证件类型 ${this.bx('id_type')} <span class="cf-dict">0.居民身份证 1.护照 2.港澳台居民居住证/通行证 4.旅行证件 9.其他</span>`],
            [37, `身份证件号码 ${this.ub('id_card', 40)}`],
        ])}</td></tr>
        <tr class="cf-line"><td>身份证件不详的具体原因 ${this.bx('id_unknown_reason')} <span class="cf-dict">1.三无人员 2.新生儿未办理 3.无完全民事行为能力 4.意识障碍 9.其他</span>　<span class="cf-ub" style="min-width:44mm">&nbsp;</span></td></tr>
        <tr class="cf-line"><td>${this.rt([
            [46, `出生地 ${this.ub('birth_prov', 12)} 省（区、市） ${this.ub('birth_city', 12)} 市 ${this.ub('birth_county', 12)} 县`],
            [30, `籍贯 ${this.ub('native_prov', 12)} 省（区、市） ${this.ub('native_city', 12)} 市`],
            [24, `民族 ${this.ub('nation', 14)}`],
        ])}</td></tr>
        <tr class="cf-line"><td>${this.rt([
            [29, `职业 ${this.ub('occupation', 26)}`],
            [71, `婚姻 ${this.bx('marriage')} <span class="cf-dict">1.未婚 2.已婚 3.丧偶 4.离婚 9.其他</span>`],
        ])}</td></tr>
        <tr class="cf-line"><td>${this.rt([
            [63, `现住址 ${this.ub('addr_prov', 12)} 省（区、市） ${this.ub('addr_city', 12)} 市 ${this.ub('addr_county', 12)} 县 ${this.ub('addr_detail', 30)}`],
            [20, `电话 ${this.ub('phone', 22)}`],
            [17, `邮编 ${this.ub('zip', 16)}`],
        ])}</td></tr>
        <tr class="cf-line"><td>${this.rt([
            [78, `户口地址 ${this.ub('hukou_prov', 12)} 省（区、市） ${this.ub('hukou_city', 12)} 市 ${this.ub('hukou_county', 12)} 县 ${this.ub('hukou_detail', 30)}`],
            [22, `邮编 ${this.ub('hukou_zip', 16)}`],
        ])}</td></tr>
        <tr class="cf-line"><td>${this.rt([
            [57, `工作单位及地址 ${this.ub('work_unit', 36)}`],
            [25, `电话 ${this.ub('work_phone', 22)}`],
            [18, `邮编 ${this.ub('work_zip', 16)}`],
        ])}</td></tr>
        <tr class="cf-line"><td>${this.rt([
            [21, `联系人姓名 ${this.ub('contact_name', 14)}`],
            [22, `关系 ${this.ub('contact_rel', 12)}`],
            [27, `地址 ${this.ub('contact_addr', 24)}`],
            [30, `电话 ${this.ub('contact_phone', 22)}`],
        ])}</td></tr>
        <tr class="cf-line cf-topline"><td>入院途径 ${this.bx('admit_route')} <span class="cf-dict">1.急诊 2.门诊 3.其他医疗机构转入 9.其他</span></td></tr>
        <tr class="cf-line"><td>${this.rt([
            [31, `入院时间 ${this.ubCn('admit_time', 36)}`],
            [21, `入院科别 ${this.ub('admit_dept', 18)}`],
            [23, `病房 ${this.ub('admit_ward', 18)}`],
            [25, `转科科别 ${this.ub('transfer_dept', 12)}`],
        ])}</td></tr>
        <tr class="cf-line"><td>${this.rt([
            [31, `出院时间 ${this.ubCn('discharge_time', 36)}`],
            [21, `出院科别 ${this.ub('discharge_dept', 18)}`],
            [23, `病房 ${this.ub('discharge_ward', 18)}`],
            [25, `实际住院 ${this.ub('stay_days', 10)} 天`],
        ])}</td></tr>
        <tr class="cf-line"><td>${this.rt([
            [52, `门(急)诊诊断(西医诊断) ${this.ub('diagnosis_emergency', 42)}`],
            [22, `疾病编码 ${this.ub('diagnosis_emergency_code', 22)}`],
            [26, `确诊日期 ${this.ub('diagnose_date', 22)}`],
        ])}</td></tr>
        <tr class="cf-diagrow"><td>${this.diagTableHtml()}</td></tr>
        <tr class="cf-line"><td class="cf-note">${this.rt([
            [36, `入院病情：1.有 2.临床未确定 3.情况不明 4.无`],
            [64, `治疗转归：1.治愈 2.好转 3.未愈 5.死亡 9.其他`],
        ])}</td></tr>
        <tr class="cf-line"><td>${this.rt([
            [65, `损伤、中毒的外部原因 ${this.ub('injury_reason', 38)}`],
            [35, `疾病编码 ${this.ub('injury_code', 16)}`],
        ])}</td></tr>
        <tr class="cf-line"><td>${this.rt([
            [65, `病理诊断：${this.ub('pathology_diagnosis', 38)}`],
            [35, `疾病编码 ${this.ub('pathology_code', 14)}<br>病理号 ${this.ub('pathology_no', 14)}`],
        ])}</td></tr>
        <tr class="cf-line cf-topline"><td>${this.rt([
            [62, `药物过敏 ${this.bx('allergy')} <span class="cf-dict">1.无 2.有，过敏药物：</span>${this.ub('allergy_drugs', 28)}`],
            [38, `死亡患者尸检 ${this.bx('autopsy')} <span class="cf-dict">1.是 2.否</span>`],
        ])}</td></tr>
        <tr class="cf-line cf-topline"><td>${this.rt([
            [50, `血型 ${this.bx('blood_type')} <span class="cf-dict">1.A 2.B 3.O 4.AB 5.不详 6.未查</span>`],
            [50, `Rh ${this.bx('rh')} <span class="cf-dict">1.阴 2.阳 3.不详 4.未查</span>`],
        ])}</td></tr>
        <tr class="cf-line cf-topline"><td>${this.rt([
            [29, `科主任 ${this.ub('chief_physician', 14)}`],
            [26, `主任（副主任）医师 ${this.ub('director_physician', 14)}`],
            [20, `主治医师 ${this.ub('attending_physician', 14)}`],
            [25, `住院医师 ${this.ub('resident_physician', 14)}`],
        ])}</td></tr>
        <tr class="cf-line cf-topline"><td>${this.rt([
            [29, `责任护士 ${this.ub('charge_nurse', 14)}`],
            [26, `进修医师 ${this.ub('trainee', 14)}`],
            [20, `实习医师 ${this.ub('intern', 14)}`],
            [25, `编码员 ${this.ub('coder', 14)}`],
        ])}</td></tr>
        <tr class="cf-line cf-topline"><td>${this.rt([
            [27, `病案质量 ${this.bx('case_quality')} <span class="cf-dict">1.甲 2.乙 3.丙</span>`],
            [22, `质控医师 ${this.ub('qc_physician', 16)}`],
            [23, `质控护士 ${this.ub('qc_nurse', 16)}`],
            [28, `质控日期 ${this.ub('qc_date', 20)}`],
        ])}</td></tr>
    </tbody></table>
</div>`;
    },
});
