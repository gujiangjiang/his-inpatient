// public/js/case_front_page/home_page2.js - 住院病案首页(二) 打印版式 (表格排版, 显示必要边框)
Object.assign(CaseHome, {
    surgeryTableHtml() {
        const rows = (this.data.surgeries || []).slice();
        while (rows.length < 7) rows.push({});
        const body = rows.map(s => `<tr>
            <td>${this.fn2(s.code) === '—' ? '—' : this.fn2(s.code)}</td><td>${this.fn2(s.date)}</td>
            <td>${this.fn2(s.level)}</td><td>${this.fn2(s.category)}</td>
            <td style="text-align:left;padding-left:1.5mm">${Format.escape(s.name || '')}</td>
            <td>${this.fn2(s.surgeon)}</td><td>${this.fn2(s.a1)}</td><td>${this.fn2(s.a2)}</td>
            <td>${this.fn2(s.incision)}</td><td>${this.fn2(s.heal)}</td>
            <td>${this.fn2(s.anesthesia)}</td><td>${this.fn2(s.anes_doc)}</td>
        </tr>`).join('');
        return `<table class="cf-op">
            <colgroup>
                <col style="width:9%"><col style="width:10.5%"><col style="width:6%"><col style="width:6%">
                <col style="width:19%"><col style="width:7.5%"><col style="width:6.5%"><col style="width:6.5%">
                <col style="width:7%"><col style="width:6.5%"><col style="width:7.5%"><col style="width:8%">
            </colgroup>
            <thead>
                <tr><th rowspan="2">手术及<br>操作编码</th><th rowspan="2">手术及<br>操作日期</th><th rowspan="2">手术<br>级别</th>
                    <th rowspan="2">手术<br>类别</th><th rowspan="2">手术及操作名称</th><th colspan="3">手术及操作医师</th>
                    <th rowspan="2">切口<br>类别</th><th rowspan="2">愈合<br>等级</th><th rowspan="2">麻醉<br>方式</th><th rowspan="2">麻醉医师</th></tr>
                <tr><th>术者</th><th>Ⅰ助</th><th>Ⅱ助</th></tr>
            </thead>
            <tbody>${body}</tbody></table>`;
    },

    icuTableHtml() {
        const rows = (this.data.icu || []).slice();
        while (rows.length < 3) rows.push({});
        const body = rows.map(r => `<tr><td>${Format.escape(r.name || '')}</td>
            <td>${Format.escape(r.in || '')}</td><td>${Format.escape(r.out || '')}</td></tr>`).join('');
        return `<table class="cf-op">
            <colgroup><col style="width:34%"><col style="width:33%"><col style="width:33%"></colgroup>
            <thead><tr><th>重症监护室名称</th><th>进入重症监护室时间</th><th>转出重症监护室时间</th></tr></thead>
            <tbody>${body}</tbody></table>`;
    },

    feeLinesHtml() {
        const m = k => `<span class="cf-num">${this.money(k)}</span>`;
        const l = t => `<div class="cf-fee-line">${t}</div>`;
        const ind = '<span class="cf-indent"></span>';
        return [
            `<div class="cf-fee-line">住院费用总计（元）：<span class="cf-num">${this.money('fee_total')}</span>　　（自付金额：<span class="cf-num">${this.money('fee_self')}</span>）</div>`,
            l(`1.综合医疗服务类：　（1）一般医疗服务费：${m('fee1')}　（2）一般治疗操作费：${m('fee2')}`),
            l(`${ind}（3）护理费：${m('fee3')}　（4）其他费用：${m('fee4')}`),
            l(`2.诊断类：　（5）病理诊断费：${m('fee5')}　（6）实验室诊断费：${m('fee6')}`),
            l(`${ind}（7）影像学诊断费：${m('fee7')}　（8）临床诊断项目费：${m('fee8')}`),
            l(`3.治疗类：　（9）非手术治疗项目费：${m('fee9')}　（临床物理治疗费：${m('fee9_phys')}）`),
            l(`${ind}（10）手术治疗费：${m('fee10')}　（麻醉费：${m('fee10_anes')}　手术费：${m('fee10_op')}）`),
            l(`4.康复类：　（11）康复费：${m('fee11')}`),
            l(`5.中医类：　（12）中医治疗费：${m('fee12')}`),
            l(`6.西药类：　（13）西药费：${m('fee13')}　（抗菌药物费用：${m('fee13_anti')}）`),
            l(`7.中药类：　（14）中成药费：${m('fee14')}　（15）中草药费：${m('fee15')}`),
            l(`8.血液和血液制品类：　（16）血费：${m('fee16')}　（17）白蛋白类制品费：${m('fee17')}`),
            l(`${ind}（18）球蛋白类制品费：${m('fee18')}　（19）凝血因子类制品费：${m('fee19')}　（20）细胞因子类制品费：${m('fee20')}`),
            l(`9.耗材类：　（21）检查用一次性医用材料费：${m('fee21')}　（22）治疗用一次性医用材料费：${m('fee22')}`),
            l(`${ind}（23）手术用一次性医用材料费：${m('fee23')}`),
            l(`10.其他类：　（24）其他费：${m('fee24')}`),
        ].join('');
    },

    buildPage2() {
        return `
<div class="cf-page">
    <div class="cf-head">
        <div class="cf-head-top">
            <span class="cf-org">医疗机构 <span class="cf-fill-line">${this.h('hospital_name') || '—'}</span></span>
            <span class="cf-orgcode">（组织机构代码：<span class="cf-fill-line">${this.h('hospital_code') || '—'}</span>）</span>
        </div>
        <div class="cf-title-row"><div class="cf-title">住院病案首页(二)</div></div>
        <div class="cf-head-id">
            <span>姓名：<span class="cf-fill-line">${this.h('name') || '—'}</span></span>
            <span>ID：<span class="cf-fill-line">${this.h('mr_no') || '—'}</span></span>
            <span>第 <span class="cf-num">${this.fn('admit_times', '')}</span> 次住院</span>
            <span>住院号：<span class="cf-fill-line">${Format.escape(this.admissionNo || '')}</span></span>
        </div>
    </div>
    <table class="cf2"><colgroup><col style="width:42%"><col style="width:58%"></colgroup><tbody>
        <tr><td colspan="2" class="cf-nest">${this.surgeryTableHtml()}</td></tr>
        <tr><td>手术类别：1.择期手术 2.急诊手术</td><td class="cf-note-r">切口类别：0.0类切口　1.Ⅰ类切口　2.Ⅱ类切口　3.Ⅲ类切口<br>愈合等级：1.甲　2.乙　3.丙　9.其他</td></tr>
        <tr><td colspan="2">离院方式 ${this.bx('leave_way')} 1.医嘱离院　2.医嘱转院，拟接收医疗机构名称：<span class="cf-fill-line" style="min-width:60mm">${this.fn('leave_transfer_hospital', '')}</span></td></tr>
        <tr><td colspan="2">3.医嘱转社区卫生服务机构/乡镇卫生院，拟接收医疗机构名称：<span class="cf-fill-line" style="min-width:52mm">${this.fn('leave_transfer_community', '')}</span>　4.非医嘱离院　5.死亡　9.其他</td></tr>
        <tr><td colspan="2">是否有出院31天内再住院计划 ${this.bx('readmit_plan')} 1.无 2.有　　目的：<span class="cf-fill-line" style="min-width:60mm">${this.fn('readmit_purpose', '')}</span></td></tr>
        <tr><td colspan="2">颅脑损伤患者昏迷时间：入院前 <span class="cf-num">${this.vd('coma_in_d')}</span> 天 <span class="cf-num">${this.vd('coma_in_h')}</span> 小时 <span class="cf-num">${this.vd('coma_in_m')}</span> 分钟　　入院后 <span class="cf-num">${this.vd('coma_after_d')}</span> 天 <span class="cf-num">${this.vd('coma_after_h')}</span> 小时 <span class="cf-num">${this.vd('coma_after_m')}</span> 分钟</td></tr>
        <tr><td colspan="2" class="cf-feebox">${this.feeLinesHtml()}</td></tr>
        <tr><td colspan="2" class="cf-nest">${this.icuTableHtml()}</td></tr>
        <tr><td colspan="2">危重病例：${this.bx('critical_case')} 0.否 1.是　　疑难病例：${this.bx('difficult_case')} 0.否 1.是　　MDT病历：${this.bx('mdt_case')} 0.否 1.是</td></tr>
        <tr><td colspan="2">单病种病例：${this.bx('single_disease')} 0.否 1.是　　日间手术病例：${this.bx('day_surgery')} 0.否 1.是　　教学查房病历：${this.bx('teaching_round')} 0.否 1.是</td></tr>
        <tr><td colspan="2">诊断符合情况：　1.门诊与出院${this.bx('match1')}　2.入院与出院${this.bx('match2')}　3.术前与术后${this.bx('match3')}　4.临床与病理${this.bx('match4')}<br>　<span style="display:inline-block;width:16mm"></span>5.放射与病理${this.bx('match5')}　（0.未做 1.符合 2.不符合 3.不肯定）</td></tr>
        <tr><td colspan="2">抢救情况：抢救 <span class="cf-num">${this.fn('rescue_times', '0')}</span> 次 成功 <span class="cf-num">${this.fn('rescue_success', '0')}</span> 次　　临床路径管理：${this.bx('clinical_path')} 1.完成 2.变异 3.退出 4.未入</td></tr>
    </tbody></table>
    <div class="cf-foot-note">说明：医疗付费方式　1.城镇职工基本医疗保险　2.城镇居民基本医疗保险　3.新型农村合作医疗　4.贫困救助</div>
    <div class="cf-foot-note" style="text-indent:22mm">5.商业医疗保险　6.全公费　7.全自费　8.其他社会保险　9.其他</div>
</div>`;
    },
});
