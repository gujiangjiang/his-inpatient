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
        const [saved, auto] = await Promise.all([
            AjaxLoader.api('case/get?admission_no=' + encodeURIComponent(this.admissionNo), { silent: true }),
            AjaxLoader.api('case/autofill?admission_no=' + encodeURIComponent(this.admissionNo), { silent: true })
        ]);
        this.autofill = auto.data || {};
        this.data = Object.assign(this.buildAutoData(this.autofill), (saved.data && saved.data.data) || {});
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
        Dom.toast('打印功能即将上线', 'info');
    }
};
