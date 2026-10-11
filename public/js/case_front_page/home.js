// public/js/case_front_page/home.js - 住院病案首页 (结构化编辑 + 打印两页)
const CaseHome = {
    admissionNo: null,
    autofill: null,
    data: {},

    /* 首页(一) 字段 */
    sections1: [
        { title: '基本信息', fields: [
            { k: 'pay_type', l: '医疗付费方式', t: 'select', o: [['1', '1 城镇职工基本医疗保险'], ['2', '2 城镇居民基本医疗保险'], ['3', '3 新型农村合作医疗'], ['4', '4 贫困救助'], ['5', '5 商业医疗保险'], ['6', '6 全公费'], ['7', '7 全自费'], ['8', '8 其他社会保险'], ['9', '9 其他']] },
            { k: 'health_card', l: '健康卡号' },
            { k: 'mr_no', l: '病案号（ID）' },
            { k: 'admit_times', l: '第几次住院', t: 'number' },
            { k: 'name', l: '姓名', ro: 1 },
            { k: 'gender', l: '性别', t: 'select', o: [['1', '1 男'], ['2', '2 女'], ['9', '9 未说明的性别']] },
            { k: 'birth_date', l: '出生日期', t: 'date' },
            { k: 'age_y', l: '年龄（岁）', t: 'number' },
            { k: 'age_m', l: '（不足1周岁）年龄（月）', t: 'number' },
            { k: 'age_d', l: '（新生儿≤28天）年龄（天）', t: 'number' },
            { k: 'nationality', l: '国籍' },
            { k: 'baby1_weight', l: '新生儿出生体重（一孩）克' },
            { k: 'baby2_weight', l: '新生儿出生体重（二孩）克' },
            { k: 'baby_admit_weight', l: '新生儿入院体重（克）' },
            { k: 'id_type', l: '身份证件类型', t: 'select', o: [['0', '0 居民身份证'], ['1', '1 护照'], ['2', '2 港澳台居民居住证/通行证'], ['4', '4 旅行证件'], ['9', '9 其他']] },
            { k: 'id_card', l: '身份证件号码', ro: 1 },
            { k: 'id_unknown_reason', l: '证件不详的具体原因', t: 'select', o: [['', ''], ['1', '1 三无人员'], ['2', '2 新生儿未办理'], ['3', '3 无完全民事行为能力'], ['4', '4 意识障碍'], ['9', '9 其他']] },
            { k: 'birth_prov', l: '出生地（省）' },
            { k: 'birth_city', l: '市' },
            { k: 'birth_county', l: '县' },
            { k: 'native_prov', l: '籍贯（省）' },
            { k: 'native_city', l: '市' },
            { k: 'nation', l: '民族' },
            { k: 'occupation', l: '职业' },
            { k: 'marriage', l: '婚姻', t: 'select', o: [['1', '1 未婚'], ['2', '2 已婚'], ['3', '3 丧偶'], ['4', '4 离婚'], ['9', '9 其他']] },
        ] },
        { title: '地址与联系人', fields: [
            { k: 'addr_prov', l: '现住址（省）' }, { k: 'addr_city', l: '市' }, { k: 'addr_county', l: '县' },
            { k: 'addr_detail', l: '现地址', span: 2 }, { k: 'phone', l: '电话' }, { k: 'zip', l: '邮编' },
            { k: 'hukou_prov', l: '户口地址（省）' }, { k: 'hukou_city', l: '市' }, { k: 'hukou_county', l: '县' },
            { k: 'hukou_detail', l: '户口地址', span: 2 }, { k: 'hukou_zip', l: '邮编' },
            { k: 'work_unit', l: '工作单位及地址', span: 2 }, { k: 'work_phone', l: '电话' }, { k: 'work_zip', l: '邮编' },
            { k: 'contact_name', l: '联系人姓名' }, { k: 'contact_rel', l: '关系' },
            { k: 'contact_addr', l: '地址', span: 2 }, { k: 'contact_phone', l: '电话' },
        ] },
        { title: '入院与出院', fields: [
            { k: 'admit_route', l: '入院途径', t: 'select', o: [['1', '1 急诊'], ['2', '2 门诊'], ['3', '3 其他医疗机构转入'], ['9', '9 其他']] },
            { k: 'admit_time', l: '入院时间', ro: 1 },
            { k: 'admit_dept', l: '入院科别', ro: 1 },
            { k: 'admit_ward', l: '病房', ro: 1 },
            { k: 'transfer_dept', l: '转科科别' },
            { k: 'discharge_time', l: '出院时间' },
            { k: 'discharge_dept', l: '出院科别' },
            { k: 'discharge_ward', l: '病房' },
            { k: 'stay_days', l: '实际住院（天）', t: 'number' },
            { k: 'allergy', l: '药物过敏', t: 'select', o: [['1', '1 无'], ['2', '2 有']] },
            { k: 'allergy_drugs', l: '过敏药物' },
            { k: 'autopsy', l: '死亡患者尸检', t: 'select', o: [['1', '1 是'], ['2', '2 否']] },
            { k: 'blood_type', l: '血型', t: 'select', o: [['1', '1 A'], ['2', '2 B'], ['3', '3 O'], ['4', '4 AB'], ['5', '5 不详'], ['6', '6 未查']] },
            { k: 'rh', l: 'Rh', t: 'select', o: [['1', '1 阴'], ['2', '2 阳'], ['3', '3 不详'], ['4', '4 未查']] },
        ] },
        { title: '医师签名与质控', fields: [
            { k: 'chief_physician', l: '科主任' }, { k: 'director_physician', l: '主任（副主任）医师' },
            { k: 'attending_physician', l: '主治医师' }, { k: 'resident_physician', l: '住院医师' },
            { k: 'charge_nurse', l: '责任护士' }, { k: 'trainee', l: '进修医师' },
            { k: 'intern', l: '实习医师' }, { k: 'coder', l: '编码员' },
            { k: 'case_quality', l: '病案质量', t: 'select', o: [['1', '1 甲'], ['2', '2 乙'], ['3', '3 丙']] },
            { k: 'qc_physician', l: '质控医师' }, { k: 'qc_nurse', l: '质控护士' }, { k: 'qc_date', l: '质控日期', t: 'date' },
        ] },
    ],

    /* 首页(二) 字段 */
    sections2: [
        { title: '离院与再住院', fields: [
            { k: 'leave_way', l: '离院方式', t: 'select', o: [['1', '1 医嘱离院'], ['2', '2 医嘱转院'], ['3', '3 医嘱转社区/乡镇'], ['4', '4 非医嘱离院'], ['5', '5 死亡'], ['9', '9 其他']] },
            { k: 'leave_transfer_hospital', l: '拟接收医疗机构名称' },
            { k: 'leave_transfer_community', l: '拟接收社区/乡镇机构名称' },
            { k: 'readmit_plan', l: '出院31天内再住院计划', t: 'select', o: [['1', '1 无'], ['2', '2 有']] },
            { k: 'readmit_purpose', l: '再住院目的' },
            { k: 'coma_in_d', l: '颅脑损伤昏迷（入院前）天', t: 'number' },
            { k: 'coma_in_h', l: '小时', t: 'number' },
            { k: 'coma_in_m', l: '分钟', t: 'number' },
            { k: 'coma_after_d', l: '（入院后）天', t: 'number' },
            { k: 'coma_after_h', l: '小时', t: 'number' },
            { k: 'coma_after_m', l: '分钟', t: 'number' },
        ] },
        { title: '诊断补充与符合情况', fields: [
            { k: 'diagnose_date', l: '确诊日期', t: 'date' },
            { k: 'injury_reason', l: '损伤、中毒的外部原因' },
            { k: 'injury_code', l: '（损伤中毒）疾病编码' },
            { k: 'pathology_diagnosis', l: '病理诊断' },
            { k: 'pathology_code', l: '（病理）疾病编码' },
            { k: 'pathology_no', l: '病理号' },
            { k: 'match1', l: '诊断符合·门诊与出院', t: 'select', o: [['', ''], ['0', '0 未做'], ['1', '1 符合'], ['2', '2 不符合'], ['3', '3 不肯定']] },
            { k: 'match2', l: '诊断符合·入院与出院', t: 'select', o: [['', ''], ['0', '0 未做'], ['1', '1 符合'], ['2', '2 不符合'], ['3', '3 不肯定']] },
            { k: 'match3', l: '诊断符合·术前与术后', t: 'select', o: [['', ''], ['0', '0 未做'], ['1', '1 符合'], ['2', '2 不符合'], ['3', '3 不肯定']] },
            { k: 'match4', l: '诊断符合·临床与病理', t: 'select', o: [['', ''], ['0', '0 未做'], ['1', '1 符合'], ['2', '2 不符合'], ['3', '3 不肯定']] },
            { k: 'match5', l: '诊断符合·放射与病理', t: 'select', o: [['', ''], ['0', '0 未做'], ['1', '1 符合'], ['2', '2 不符合'], ['3', '3 不肯定']] },
        ] },
        { title: '病例与质控', fields: [
            { k: 'critical_case', l: '危重病例', t: 'select', o: [['0', '0 否'], ['1', '1 是']] },
            { k: 'difficult_case', l: '疑难病例', t: 'select', o: [['0', '0 否'], ['1', '1 是']] },
            { k: 'mdt_case', l: 'MDT 病历', t: 'select', o: [['0', '0 否'], ['1', '1 是']] },
            { k: 'single_disease', l: '单病种病例', t: 'select', o: [['0', '0 否'], ['1', '1 是']] },
            { k: 'day_surgery', l: '日间手术病例', t: 'select', o: [['0', '0 否'], ['1', '1 是']] },
            { k: 'teaching_round', l: '教学查房病历', t: 'select', o: [['0', '0 否'], ['1', '1 是']] },
            { k: 'rescue_times', l: '抢救次数', t: 'number' },
            { k: 'rescue_success', l: '抢救成功次数', t: 'number' },
            { k: 'clinical_path', l: '临床路径管理', t: 'select', o: [['1', '1 完成'], ['2', '2 变异'], ['3', '3 退出'], ['4', '4 未入']] },
        ] },
    ],

    /* 费用项目 (24 项) */
    feeItems: [
        ['fee1', '（1）一般医疗服务费'], ['fee2', '（2）一般治疗操作费'], ['fee3', '（3）护理费'], ['fee4', '（4）其他费用'],
        ['fee5', '（5）病理诊断费'], ['fee6', '（6）实验室诊断费'], ['fee7', '（7）影像学诊断费'], ['fee8', '（8）临床诊断项目费'],
        ['fee9', '（9）非手术治疗项目费'], ['fee9_phys', '（临床物理治疗费）'], ['fee10', '（10）手术治疗费'], ['fee10_anes', '（麻醉费）'], ['fee10_op', '（手术费）'],
        ['fee11', '（11）康复费'], ['fee12', '（12）中医治疗费'], ['fee13', '（13）西药费'], ['fee13_anti', '（抗菌药物费用）'],
        ['fee14', '（14）中成药费'], ['fee15', '（15）中草药费'], ['fee16', '（16）血费'], ['fee17', '（17）白蛋白类制品费'],
        ['fee18', '（18）球蛋白类制品费'], ['fee19', '（19）凝血因子类制品费'], ['fee20', '（20）细胞因子类制品费'],
        ['fee21', '（21）检查用一次性医用材料费'], ['fee22', '（22）治疗用一次性医用材料费'], ['fee23', '（23）手术用一次性医用材料费'], ['fee24', '（24）其他费'],
    ],

    async init() {
        const p = PatientContext.current();
        if (!p || !p.admission_no) { Dom.toast('请先选择患者', 'info'); return; }
        this.admissionNo = p.admission_no;
        const [saved, auto, st, mr] = await Promise.all([
            AjaxLoader.api('case/get?admission_no=' + encodeURIComponent(this.admissionNo), { silent: true }),
            AjaxLoader.api('case/autofill?admission_no=' + encodeURIComponent(this.admissionNo), { silent: true }),
            AjaxLoader.api('auth/setup-status', { silent: true }),
            AjaxLoader.api('emr/diagnosis-match-get?patient_id=' + p.id, { silent: true }).catch(() => ({ data: {} }))
        ]);
        this.autofill = auto.data || {};
        const autoData = this.buildAutoData(this.autofill);
        // 诊断符合情况默认取自诊断模块
        const m = mr.data || {};
        autoData.match1 = m.out_in || ''; autoData.match2 = m.adm_out || '';
        autoData.match3 = m.pre_post || ''; autoData.match4 = m.clin_path || ''; autoData.match5 = m.rad_path || '';
        // 机构名默认
        if (st.data && st.data.hospital_name) autoData.hospital_name = st.data.hospital_name;
        this.data = Object.assign(autoData, (saved.data && saved.data.data) || {});
        this.render();
        this.fill();
    },

    buildAutoData(a) {
        const p = a.patient || {};
        const asg = a.assignment || {};
        const dg = a.diagnoses || {};
        const em = dg.emergency || {};
        const ad = dg.admission || {};
        let days = '';
        if (p.admission_date) {
            const d = new Date(String(p.admission_date).replace(/-/g, '/'));
            if (!isNaN(d.getTime())) days = Math.max(1, Math.ceil((Date.now() - d.getTime()) / 86400000));
        }
        const auto = {
            name: p.name || '',
            gender: p.gender === 'female' ? '2' : (p.gender === 'male' ? '1' : '9'),
            birth_date: p.birth_date || '',
            id_card: p.id_card || '',
            nationality: '中国',
            admit_time: p.admission_date || '',
            admit_dept: p.department_name || '',
            admit_ward: [p.ward_name, p.bed_no ? p.bed_no + '床' : ''].filter(Boolean).join(' '),
            stay_days: days,
            chief_physician: asg.director_name || '',
            director_physician: asg.director_name || '',
            attending_physician: asg.senior_name || '',
            resident_physician: asg.attending_name || '',
            diagnosis_emergency: em.name || p.admission_diagnosis || '',
            diagnosis_emergency_code: em.icd_code || p.admission_icd_code || '',
            diagnosis_admission: ad.name || '',
            diagnosis_admission_code: ad.icd_code || '',
            allergy: 1,
            blood_type: '',
            rh: '',
        };
        auto.discharge_diags = (dg.discharge_list || []).map(d => ({ name: d.name || '', code: d.icd_code || '', in_status: '', outcome: '' }));
        auto.surgeries = a.surgeries || [];
        auto.icu = [{ name: '', in: '', out: '' }];
        Object.assign(auto, a.fee || {});
        return auto;
    },

    fieldHtml(f) {
        const v = this.data[f.k] !== undefined && this.data[f.k] !== null ? String(this.data[f.k]) : '';
        const span = f.span || 1;
        if (f.t === 'select') {
            const opts = (f.o || []).map(([val, label]) =>
                `<option value="${val}" ${v === String(val) ? 'selected' : ''}>${Format.escape(label)}</option>`).join('');
            return `<div class="form-group cf-span-${span}"><label class="form-label">${Format.escape(f.l)}</label>
                <select class="form-control" data-cf="${f.k}">${opts}</select></div>`;
        }
        const type = f.t === 'number' ? 'number' : (f.t === 'date' ? 'date' : 'text');
        return `<div class="form-group cf-span-${span}"><label class="form-label">${Format.escape(f.l)}</label>
            <input type="${type}" ${f.ro ? 'readonly' : ''} class="form-control" data-cf="${f.k}" value="${Format.escape(v)}"></div>`;
    },

    render() {
        const s1 = this.sections1.map(sec => `
            <div class="card"><div class="card-header">${sec.title}</div>
            <div class="cf-grid">${sec.fields.map(f => this.fieldHtml(f)).join('')}</div></div>`).join('');
        document.getElementById('cf-pane1').innerHTML = s1;

        const s2 = this.sections2.map(sec => `
            <div class="card"><div class="card-header">${sec.title}</div>
            <div class="cf-grid">${sec.fields.map(f => this.fieldHtml(f)).join('')}</div></div>`).join('');

        const dgRows = (this.data.discharge_diags || []).map((d, i) => `
            <tr>
                <td>${Format.escape(d.name || '')}</td>
                <td>${Format.escape(d.code || '')}</td>
                <td><select class="form-control" data-cf-diag="${i}" data-field="in_status">
                    ${[['', ''], ['1', '1 有'], ['2', '2 临床未确定'], ['3', '3 情况不明'], ['4', '4 无']].map(([v, t]) =>
                        `<option value="${v}" ${String(d.in_status) === v ? 'selected' : ''}>${t}</option>`).join('')}
                </select></td>
                <td><select class="form-control" data-cf-diag="${i}" data-field="outcome">
                    ${[['', ''], ['1', '1 治愈'], ['2', '2 好转'], ['3', '3 未愈'], ['5', '5 死亡'], ['9', '9 其他']].map(([v, t]) =>
                        `<option value="${v}" ${String(d.outcome) === v ? 'selected' : ''}>${t}</option>`).join('')}
                </select></td>
            </tr>`).join('') || '<tr><td colspan="4"><div class="empty-state">暂无出院诊断（可在诊断页维护后刷新）</div></td></tr>';
        const sgRows = (this.data.surgeries || []).map(s => `
            <tr><td>${Format.escape(s.date || '')}</td><td>${Format.escape(s.name || '')}</td>
            <td>${Format.escape(s.surgeon || '')}</td><td>${Format.escape(s.anesthesia || '')}</td></tr>`).join('')
            || '<tr><td colspan="4"><div class="empty-state">暂无手术记录</div></td></tr>';
        const icuRows = (this.data.icu || [{ name: '', in: '', out: '' }]).map((r, i) => `
            <tr>
                <td><input class="form-control" data-cf-icu="${i}" data-field="name" value="${Format.escape(r.name || '')}"></td>
                <td><input class="form-control" data-cf-icu="${i}" data-field="in" data-dtpicker="datetime" value="${Format.escape(r.in || '')}"></td>
                <td><input class="form-control" data-cf-icu="${i}" data-field="out" data-dtpicker="datetime" value="${Format.escape(r.out || '')}"></td>
            </tr>`).join('');
        const feeGrid = this.feeItems.map(([k, label]) => `
            <div class="form-group cf-fee">
                <label class="form-label">${label}</label>
                <input type="number" class="form-control" data-cf="${k}" value="${Format.escape(String(this.data[k] ?? 0))}">
            </div>`).join('');

        document.getElementById('cf-pane2').innerHTML = s2 + `
            <div class="card"><div class="card-header">出院诊断（取自诊断模块，可补充入院病情/治疗转归）</div>
                <table class="table"><thead><tr><th>出院诊断</th><th>疾病编码</th><th>入院病情</th><th>治疗转归</th></tr></thead>
                <tbody>${dgRows}</tbody></table></div>
            <div class="card"><div class="card-header">手术及操作（取自手术记录）</div>
                <table class="table"><thead><tr><th>手术日期</th><th>手术及操作名称</th><th>术者</th><th>麻醉方式</th></tr></thead>
                <tbody>${sgRows}</tbody></table></div>
            <div class="card"><div class="card-header">住院费用（元）
                <button class="btn btn-small" style="float:right" onclick="CaseHome.addIcu()">添加监护记录</button></div>
                <div class="cf-grid">
                    <div class="form-group"><label class="form-label">住院费用总计</label><input type="number" class="form-control" data-cf="fee_total" value="${Format.escape(String(this.data.fee_total ?? 0))}"></div>
                    <div class="form-group"><label class="form-label">自付金额</label><input type="number" class="form-control" data-cf="fee_self" value="${Format.escape(String(this.data.fee_self ?? 0))}"></div>
                </div>
                <div class="cf-fee-grid">${feeGrid}</div>
                <div class="card-header" style="margin-top:14px">重症监护室</div>
                <table class="table"><thead><tr><th>重症监护室名称</th><th>进入时间</th><th>转出时间</th></tr></thead>
                <tbody id="cf-icu-body">${icuRows}</tbody></table>
            </div>`;
        if (window.DateTimePicker) DateTimePicker.initInputs(document.getElementById('cf-pane2'));
        if (window.SelectPicker) SelectPicker.initInputs(document.getElementById('cf-pane1'));
    },

    addIcu() {
        this.collect();
        this.data.icu = (this.data.icu || []).concat([{ name: '', in: '', out: '' }]);
        const tbody = document.getElementById('cf-icu-body');
        this.render();
    },

    fill() { /* 初始值已随渲染填充 */ },

    collect() {
        document.querySelectorAll('#cf-pane1 [data-cf], #cf-pane2 [data-cf]').forEach(el => {
            this.data[el.dataset.cf] = el.value;
        });
        document.querySelectorAll('#cf-pane2 [data-cf-diag]').forEach(el => {
            const i = Number(el.dataset.cfDiag);
            if (this.data.discharge_diags && this.data.discharge_diags[i]) {
                this.data.discharge_diags[i][el.dataset.field] = el.value;
            }
        });
        document.querySelectorAll('#cf-pane2 [data-cf-icu]').forEach(el => {
            const i = Number(el.dataset.cfIcu);
            if (!this.data.icu) this.data.icu = [];
            if (!this.data.icu[i]) this.data.icu[i] = { name: '', in: '', out: '' };
            this.data.icu[i][el.dataset.field] = el.value;
        });
    },

    async save() {
        this.collect();
        const r = await AjaxLoader.api('case/save', { method: 'POST', data: {
            admission_no: this.admissionNo, data: this.data
        }});
        if (r.status === 'success') Dom.toast('病案首页已保存', 'success');
    },

    print() {
        this.collect();
        const html = '<style>' + this.printCss() + '</style>' + this.buildPage1() + '<div class="cf-pagebreak"></div>' + this.buildPage2();
        PrintHelper.printHtml('住院病案首页', html);
    },

    /* 值渲染: 空值输出下划线空位 */
    v(k) {
        const x = this.data[k];
        if (x === '' || x === undefined || x === null) return '<span class="cf-v"></span>';
        return Format.escape(String(x));
    },

    box(v) {
        const s = (v === '' || v === undefined || v === null) ? '' : String(v);
        return '<b class="cf-box">' + (s === '' ? '&nbsp;' : Format.escape(s)) + '</b>';
    },

    printCss() {
        return `
        .cf-page { width: 210mm; min-height: 296mm; padding: 10mm 12mm; margin: 0 auto 24px; background: #fff; box-shadow: 0 2px 12px rgba(0,0,0,.18); box-sizing: border-box; font-size: 10.5pt; color: #000; line-height: 1.9; }
        .cf-pagebreak { page-break-after: always; }
        @page { size: A4; margin: 8mm; }
        @media print { body { margin: 0; background: #fff; } .cf-page { box-shadow: none; margin: 0; width: auto; min-height: auto; padding: 0; } }
        .cf-p1-head { position: relative; margin-bottom: 4px; }
        .cf-org { font-size: 10.5pt; }
        .cf-title { text-align: center; font-size: 16pt; font-weight: 700; letter-spacing: 6px; margin: 2px 0 6px; }
        .cf-subline { display: flex; justify-content: space-between; font-size: 9.5pt; margin-bottom: 2px; }
        .cf-line { margin: 2px 0; word-break: break-all; }
        .cf-lb { color: #000; }
        .cf-v, .cf-ub { display: inline-block; min-width: 60px; border-bottom: 1px solid #000; padding: 0 4px; text-align: center; }
        .cf-ub { min-width: 90px; }
        .cf-box { display: inline-block; min-width: 15px; height: 15px; line-height: 15px; text-align: center; border: 1px solid #000; font-weight: 600; font-style: italic; margin: 0 2px; padding: 0 1px; }
        .cf-codes { font-weight: 400; }
        .cf-table { width: 100%; border-collapse: collapse; margin: 4px 0; }
        .cf-table th, .cf-table td { border: 1px solid #000; padding: 2px 5px; font-size: 10pt; height: 20px; }
        .cf-table th { background: #f2f2f2; font-weight: 600; text-align: center; }
        .cf-fee-grid2 { display: flex; flex-wrap: wrap; }
        .cf-fee-grid2 .cf-fee-item { width: 50%; box-sizing: border-box; padding-right: 10px; font-size: 9.5pt; line-height: 2.0; }
        .cf-fee-val { display: inline-block; min-width: 58px; border-bottom: 1px solid #000; text-align: right; padding: 0 3px; }
        .cf-note { font-size: 9pt; color: #333; }
        .cf-consent { text-align: center; font-size: 9pt; color: #888; margin-top: 6px; }`;
    },

    buildPage1() {
        const d = this.data;
        const p = (this.autofill || {}).patient || {};
        const diags = (d.discharge_diags && d.discharge_diags.length) ? d.discharge_diags : [{}, {}];
        const diagRows = [];
        for (let i = 0; i < Math.max(12, diags.length); i++) {
            const r = diags[i] || {};
            diagRows.push(`<tr>
                <td>${i === 0 ? '主要诊断' : (r.name ? '其他诊断' : '')} ${Format.escape(r.name || '')}</td>
                <td>${Format.escape(r.code || '')}</td>
                <td>${Format.escape(r.in_status === '1' ? '有' : r.in_status === '2' ? '临床未确定' : r.in_status === '3' ? '情况不明' : r.in_status === '4' ? '无' : '')}</td>
                <td>${Format.escape(r.outcome === '1' ? '治愈' : r.outcome === '2' ? '好转' : r.outcome === '3' ? '未愈' : r.outcome === '5' ? '死亡' : r.outcome === '9' ? '其他' : '')}</td>
            </tr>`);
        }
        return `
        <div class="cf-page">
            <div class="cf-p1-head">
                <div class="cf-org">医疗机构：<span class="cf-ub">${this.v('hospital_name') || Format.escape(p.hospital || '')}</span>（组织机构代码：<span class="cf-ub"></span>）</div>
                <div class="cf-title">住院病案首页（一）</div>
                <div class="cf-subline">
                    <span>健康卡号：<span class="cf-ub">${this.v('health_card')}</span></span>
                    <span>ID：<span class="cf-ub">${this.v('mr_no')}</span></span>
                    <span>第 ${this.box(d.admit_times)} 次住院</span>
                    <span>住院号：<span class="cf-ub">${Format.escape(this.admissionNo || '')}</span></span>
                </div>
            </div>
            <div class="cf-line">医疗付费方式：${this.box(d.pay_type)}<span class="cf-codes">1.城镇职工基本医疗保险 2.城镇居民基本医疗保险 3.新型农村合作医疗 4.贫困救助 5.商业医疗保险 6.全公费 7.全自费 8.其他社会保险 9.其他</span></div>
            <div class="cf-line">姓名 <span class="cf-ub">${this.v('name')}</span> 性别 ${this.box(d.gender)}<span class="cf-codes">0.未知的性别 1.男 2.女 9.未说明的性别</span> 出生日期 <span class="cf-ub">${this.v('birth_date')}</span></div>
            <div class="cf-line">年龄 <span class="cf-ub">${this.v('age_y')}</span> 岁 国籍 <span class="cf-ub">${this.v('nationality')}</span>（年龄不足1周岁的）年龄 <span class="cf-ub">${this.v('age_m')}</span> 月（新生儿≤28天的）年龄 <span class="cf-ub">${this.v('age_d')}</span> 天</div>
            <div class="cf-line">新生儿出生体重（一孩 <span class="cf-ub">${this.v('baby1_weight')}</span> 克 二孩 <span class="cf-ub">${this.v('baby2_weight')}</span> 克）新生儿入院体重 <span class="cf-ub">${this.v('baby_admit_weight')}</span> 克</div>
            <div class="cf-line">身份证件类型 ${this.box(d.id_type)}<span class="cf-codes">0.居民身份证 1.护照 2.港澳台居民居住证/通行证 4.旅行证件 9.其他</span> 身份证件号码 <span class="cf-ub">${this.v('id_card')}</span></div>
            <div class="cf-line">身份证件不详的具体原因 ${this.box(d.id_unknown_reason)} 1.三无人员 2.新生儿未办理 3.无完全民事行为能力 4.意识障碍 9.其他 <span class="cf-ub">${this.v('b')}</span></div>
            <div class="cf-line">出生地 <span class="cf-ub">${this.v('birth_prov')}</span> 省（区、市）<span class="cf-ub">${this.v('birth_city')}</span> 市 <span class="cf-ub">${this.v('birth_county')}</span> 县 籍贯 <span class="cf-ub">${this.v('native_prov')}</span> 省（区、市）<span class="cf-ub">${this.v('native_city')}</span> 市 民族 <span class="cf-ub">${this.v('nation')}</span></div>
            <div class="cf-line">职业 <span class="cf-ub">${this.v('occupation')}</span> 婚姻 ${this.box(d.marriage)} 1.未婚 2.已婚 3.丧偶 4.离婚 9.其他</div>
            <div class="cf-line">现住址 <span class="cf-ub">${this.v('addr_prov')}</span> 省（区、市）<span class="cf-ub">${this.v('addr_city')}</span> 市 <span class="cf-ub">${this.v('addr_county')}</span> 县 <span class="cf-ub">${this.v('addr_detail')}</span> 电话 <span class="cf-ub">${this.v('phone')}</span> 邮编 <span class="cf-ub">${this.v('zip')}</span></div>
            <div class="cf-line">户口地址 <span class="cf-ub">${this.v('hukou_prov')}</span> 省（区、市）<span class="cf-ub">${this.v('hukou_city')}</span> 市 <span class="cf-ub">${this.v('hukou_county')}</span> 县 <span class="cf-ub">${this.v('hukou_detail')}</span> 邮编 <span class="cf-ub">${this.v('hukou_zip')}</span></div>
            <div class="cf-line">工作单位及地址 <span class="cf-ub">${this.v('work_unit')}</span> 电话 <span class="cf-ub">${this.v('work_phone')}</span> 邮编 <span class="cf-ub">${this.v('work_zip')}</span></div>
            <div class="cf-line">联系人姓名 <span class="cf-ub">${this.v('contact_name')}</span> 关系 <span class="cf-ub">${this.v('contact_rel')}</span> 地址 <span class="cf-ub">${this.v('contact_addr')}</span> 电话 <span class="cf-ub">${this.v('contact_phone')}</span></div>
            <div class="cf-line">入院途径 ${this.box(d.admit_route)} 1.急诊 2.门诊 3.其他医疗机构转入 9.其他</div>
            <div class="cf-line">入院时间 <span class="cf-ub">${this.v('admit_time')}</span> 入院科别 <span class="cf-ub">${this.v('admit_dept')}</span> 病房 <span class="cf-ub">${this.v('admit_ward')}</span> 转科科别 <span class="cf-ub">${this.v('transfer_dept')}</span></div>
            <div class="cf-line">出院时间 <span class="cf-ub">${this.v('discharge_time')}</span> 出院科别 <span class="cf-ub">${this.v('discharge_dept')}</span> 病房 <span class="cf-ub">${this.v('discharge_ward')}</span> 实际住院 <span class="cf-ub">${this.v('stay_days')}</span> 天</div>
            <div class="cf-line">门（急）诊诊断（西医诊断） <span class="cf-ub">${this.v('diagnosis_emergency')}</span> 疾病编码 <span class="cf-ub">${this.v('diagnosis_emergency_code')}</span> 确诊日期 <span class="cf-ub">${this.v('diagnose_date')}</span></div>
            <table class="cf-table">
                <thead><tr><th style="width:46%">出院诊断</th><th style="width:22%">疾病编码</th><th style="width:16%">入院病情</th><th style="width:16%">治疗转归</th></tr></thead>
                <tbody>${diagRows.join('')}</tbody>
            </table>
            <div class="cf-line cf-note">入院病情：1.有 2.临床未确定 3.情况不明 4.无　　治疗转归：1.治愈 2.好转 3.未愈 5.死亡 9.其他</div>
            <div class="cf-line">损伤、中毒的外部原因 <span class="cf-ub">${this.v('injury_reason')}</span> 疾病编码 <span class="cf-ub">${this.v('injury_code')}</span></div>
            <div class="cf-line">病理诊断 <span class="cf-ub">${this.v('pathology_diagnosis')}</span> 疾病编码 <span class="cf-ub">${this.v('pathology_code')}</span> 病理号 <span class="cf-ub">${this.v('pathology_no')}</span></div>
            <div class="cf-line">药物过敏 ${this.box(d.allergy)} 1.无 2.有，过敏药物：<span class="cf-ub">${this.v('allergy_drugs')}</span> 死亡患者尸检 ${this.box(d.autopsy)} 1.是 2.否</div>
            <div class="cf-line">血型 ${this.box(d.blood_type)} 1.A 2.B 3.O 4.AB 5.不详 6.未查　　Rh ${this.box(d.rh)} 1.阴 2.阳 3.不详 4.未查</div>
            <div class="cf-line">科主任 <span class="cf-ub">${this.v('chief_physician')}</span> 主任（副主任）医师 <span class="cf-ub">${this.v('director_physician')}</span> 主治医师 <span class="cf-ub">${this.v('attending_physician')}</span> 住院医师 <span class="cf-ub">${this.v('resident_physician')}</span></div>
            <div class="cf-line">责任护士 <span class="cf-ub">${this.v('charge_nurse')}</span> 进修医师 <span class="cf-ub">${this.v('trainee')}</span> 实习医师 <span class="cf-ub">${this.v('intern')}</span> 编码员 <span class="cf-ub">${this.v('coder')}</span></div>
            <div class="cf-line">病案质量 ${this.box(d.case_quality)} 1.甲 2.乙 3.丙　质控医师 <span class="cf-ub">${this.v('qc_physician')}</span> 质控护士 <span class="cf-ub">${this.v('qc_nurse')}</span> 质控日期 <span class="cf-ub">${this.v('qc_date')}</span></div>
        </div>`;
    },

    buildPage2() {
        const d = this.data;
        const sgs = (d.surgeries || []).slice();
        while (sgs.length < 7) sgs.push({});
        const sgRows = sgs.map(s => `<tr>
            <td>${Format.escape(s.code || '')}</td><td>${Format.escape(s.date || '')}</td>
            <td>${Format.escape(s.level || '')}</td><td>${Format.escape(s.category || '')}</td>
            <td>${Format.escape(s.name || '')}</td><td>${Format.escape(s.surgeon || '')}</td>
            <td>${Format.escape(s.a1 || '')}</td><td>${Format.escape(s.a2 || '')}</td>
            <td>${Format.escape(s.incision || '')}</td><td>${Format.escape(s.heal || '')}</td>
            <td>${Format.escape(s.anesthesia || '')}</td><td>${Format.escape(s.anes_doc || '')}</td>
        </tr>`).join('');
        const icu = (d.icu || []).slice();
        while (icu.length < 3) icu.push({});
        const icuRows = icu.map(r => `<tr><td>${Format.escape(r.name || '')}</td><td>${Format.escape(r.in || '')}</td><td>${Format.escape(r.out || '')}</td></tr>`).join('');
        const feeLine = ([k, label]) =>
            `<div class="cf-fee-item">${label} <span class="cf-fee-val">${this.v(k)}</span></div>`;
        const fees = this.feeItems.map(feeLine).join('');
        return `
        <div class="cf-page">
            <div class="cf-p1-head">
                <div class="cf-org">医疗机构：<span class="cf-ub"></span>（组织机构代码：<span class="cf-ub"></span>）</div>
                <div class="cf-title">住院病案首页（二）</div>
                <div class="cf-subline">
                    <span>姓名：<span class="cf-ub">${this.v('name')}</span></span>
                    <span>ID：<span class="cf-ub">${this.v('mr_no')}</span></span>
                    <span>第 ${this.box(d.admit_times)} 次住院</span>
                    <span>住院号：<span class="cf-ub">${Format.escape(this.admissionNo || '')}</span></span>
                </div>
            </div>
            <table class="cf-table">
                <thead><tr>
                    <th>手术及操作编码</th><th>手术及操作日期</th><th>手术级别</th><th>手术类别</th>
                    <th style="width:20%">手术及操作名称</th>
                    <th colspan="3">手术及操作医师</th>
                    <th>切口类别</th><th>愈合等级</th><th>麻醉方式</th><th>麻醉医师</th>
                </tr><tr>
                    <th></th><th></th><th></th><th></th><th></th><th>术者</th><th>Ⅰ助</th><th>Ⅱ助</th><th></th><th></th><th></th><th></th>
                </tr></thead>
                <tbody>${sgRows}</tbody>
            </table>
            <div class="cf-line cf-note">手术类别：1.择期手术 2.急诊手术　　切口类别：0.0类切口 1.Ⅰ类切口 2.Ⅱ类切口 3.Ⅲ类切口　　愈合等级：1.甲 2.乙 3.丙 9.其他</div>
            <div class="cf-line">离院方式 ${this.box(d.leave_way)} 1.医嘱离院 2.医嘱转院，拟接收医疗机构名称：<span class="cf-ub">${this.v('leave_transfer_hospital')}</span></div>
            <div class="cf-line">　3.医嘱转社区卫生服务机构/乡镇卫生院，拟接收医疗机构名称：<span class="cf-ub">${this.v('leave_transfer_community')}</span> 4.非医嘱离院 5.死亡 9.其他</div>
            <div class="cf-line">是否有出院31天内再住院计划 ${this.box(d.readmit_plan)} 1.无 2.有　目的：<span class="cf-ub">${this.v('readmit_purpose')}</span></div>
            <div class="cf-line">颅脑损伤患者昏迷时间：入院前 <span class="cf-ub">${this.v('coma_in_d')}</span> 天 <span class="cf-ub">${this.v('coma_in_h')}</span> 小时 <span class="cf-ub">${this.v('coma_in_m')}</span> 分钟　入院后 <span class="cf-ub">${this.v('coma_after_d')}</span> 天 <span class="cf-ub">${this.v('coma_after_h')}</span> 小时 <span class="cf-ub">${this.v('coma_after_m')}</span> 分钟</div>
            <div class="cf-line">住院费用总计（元）：<span class="cf-ub">${this.v('fee_total')}</span>　（自付金额：<span class="cf-ub">${this.v('fee_self')}</span>）</div>
            <div class="cf-fee-grid2">${fees}</div>
            <table class="cf-table">
                <thead><tr><th>重症监护室名称</th><th>进入重症监护室时间</th><th>转出重症监护室时间</th></tr></thead>
                <tbody>${icuRows}</tbody>
            </table>
            <div class="cf-line">危重病例：${this.box(d.critical_case)} 0.否 1.是　　疑难病例：${this.box(d.difficult_case)} 0.否 1.是　　MDT病历：${this.box(d.mdt_case)} 0.否 1.是</div>
            <div class="cf-line">单病种病例：${this.box(d.single_disease)} 0.否 1.是　　日间手术病例：${this.box(d.day_surgery)} 0.否 1.是　　教学查房病历：${this.box(d.teaching_round)} 0.否 1.是</div>
            <div class="cf-line">诊断符合情况：1.门诊与出院 ${this.box(d.match1)}　2.入院与出院 ${this.box(d.match2)}　3.术前与术后 ${this.box(d.match3)}　4.临床与病理 ${this.box(d.match4)}　5.放射与病理 ${this.box(d.match5)}　（0.未做 1.符合 2.不符合 3.不肯定）</div>
            <div class="cf-line">抢救情况：抢救 <span class="cf-ub">${this.v('rescue_times')}</span> 次 成功 <span class="cf-ub">${this.v('rescue_success')}</span> 次　　临床路径管理：${this.box(d.clinical_path)} 1.完成 2.变异 3.退出 4.未入</div>
            <div class="cf-line cf-note">说明：医疗付费方式 1.城镇职工基本医疗保险 2.城镇居民基本医疗保险 3.新型农村合作医疗 4.贫困救助 5.商业医疗保险 6.全公费 7.全自费 8.其他社会保险 9.其他</div>
        </div>`;
    }
};

