// public/js/case_front_page/home.js - 住院病案首页 (结构化编辑, 自动项只读 + 费用自动)
const CaseHome = {
    admissionNo: null,
    autofill: null,
    data: {},

    async init() {
        const p = PatientContext.current();
        if (!p || !p.admission_no) { Dom.toast('请先选择患者', 'info'); return; }
        this.admissionNo = p.admission_no;
        const [saved, auto] = await Promise.all([
            AjaxLoader.api('case/get?admission_no=' + encodeURIComponent(this.admissionNo), { silent: true }),
            AjaxLoader.api('case/autofill?admission_no=' + encodeURIComponent(this.admissionNo), { silent: true }),
        ]);
        this.autofill = auto.data || {};
        this.compose((saved.data && saved.data.data) || {});
        this.render();
    },

    /* 保存值 + 自动抓取值合成 (自动项与费用始终以接口为准) */
    compose(saved) {
        const a = this.autofill || {};
        const p = a.patient || {};
        const asg = a.assignment || {};
        const dg = a.diagnoses || {};
        const em = dg.emergency || {};
        const d = Object.assign({}, saved);
        d.nationality = d.nationality || '中国';
        d.id_type = d.id_type || '0';
        d.name = p.name || d.name || '';
        d.gender = p.gender === 'female' ? '2' : (p.gender === 'male' ? '1' : (d.gender || '9'));
        d.birth_date = p.birth_date || d.birth_date || '';
        d.id_card = p.id_card || d.id_card || '';
        d.chief_physician = asg.director_name || d.chief_physician || '';
        d.director_physician = asg.director_name || d.director_physician || '';
        d.attending_physician = asg.senior_name || d.attending_physician || '';
        d.resident_physician = asg.attending_name || d.resident_physician || '';
        d.diagnosis_emergency = em.name || p.admission_diagnosis || d.diagnosis_emergency || '';
        d.diagnosis_emergency_code = em.icd_code || p.admission_icd_code || d.diagnosis_emergency_code || '';
        Object.assign(d, a.auto || {});
        Object.assign(d, a.fee || {});
        const savedDiag = {};
        (saved.discharge_diags || []).forEach(x => { savedDiag[(x.name || '') + '|' + (x.code || '')] = x; });
        d.discharge_diags = (dg.discharge_list || []).map(x => {
            const sv = savedDiag[(x.name || '') + '|' + (x.icd_code || '')] || {};
            return { name: x.name || '', code: x.icd_code || '', in_status: sv.in_status || '', outcome: sv.outcome || '' };
        });
        d.surgeries = a.surgeries || [];
        if (!Array.isArray(d.icu) || !d.icu.length) d.icu = [{ name: '', in: '', out: '' }];
        delete d.diagnosis_admission;
        delete d.diagnosis_admission_code;
        this.data = d;
    },

    /* 数字/文本安全取值 */
    raw(k) {
        const x = this.data[k];
        return (x === '' || x === undefined || x === null) ? '' : String(x);
    },

    /* 选项显示文本: 编辑态去掉预置编号 (如 "1 男" → "男", "0 未知的性别" → "未知的性别") */
    optText(label) {
        return String(label === undefined || label === null ? '' : label)
            .replace(/^\s*\d+\s*[.、]\s*/, '')
            .replace(/^\s*\d+\s+/, '');
    },

    optLabel(f, v) {
        const opts = typeof f.o === 'string' ? (CaseHomeFields[f.o] || []) : (f.o || []);
        const hit = opts.find(o => String(o[0]) === String(v));
        return hit ? this.optText(hit[1]) : String(v || '');
    },

    fieldHtml(f) {
        const v = this.raw(f.k);
        const span = f.span || 1;
        const label = Format.escape(f.l);
        if (f.auto) {
            const disp = (f.t === 'select') ? this.optLabel(f, v) : v;
            return `<div class="form-group cf-span-${span}"><label class="form-label">${label}<i class="cf-auto-tag">自动</i></label>
                <input type="text" readonly class="form-control cf-readonly" value="${Format.escape(disp)}"></div>`;
        }
        if (f.t === 'select') {
            const opts = (typeof f.o === 'string' ? CaseHomeFields[f.o] : f.o) || [];
            const html = opts.map(o =>
                `<option value="${o[0]}" ${v === String(o[0]) ? 'selected' : ''}>${Format.escape(this.optText(o[1]))}</option>`).join('');
            return `<div class="form-group cf-span-${span}"><label class="form-label">${label}</label>
                <select class="form-control" data-cf="${f.k}">${html}</select></div>`;
        }
        const type = f.t === 'number' ? 'number' : (f.t === 'date' ? 'date' : 'text');
        return `<div class="form-group cf-span-${span}"><label class="form-label">${label}</label>
            <input type="${type}" class="form-control" data-cf="${f.k}" value="${Format.escape(v)}"></div>`;
    },

    sectionHtml(sec) {
        return `<div class="card"><div class="card-header">${Format.escape(sec.title)}</div>
            <div class="cf-grid">${sec.fields.map(f => this.fieldHtml(f)).join('')}</div></div>`;
    },

    render() {
        document.getElementById('cf-pane1').innerHTML = CaseHomeFields.sections1.map(s => this.sectionHtml(s)).join('');
        document.getElementById('cf-pane2').innerHTML = CaseHomeFields.sections2.map(s => this.sectionHtml(s)).join('')
            + this.diagCard() + this.surgeryCard() + this.feeCard() + this.icuCard();
        if (window.DateTimePicker) DateTimePicker.initInputs(document.getElementById('cf-pane2'));
        if (window.SelectPicker) SelectPicker.initInputs(document.getElementById('cf-pane1'));
        if (window.SelectPicker) SelectPicker.initInputs(document.getElementById('cf-pane2'));
    },

    /* 出院诊断: 诊断/编码自动, 入院病情/治疗转归手工补充 */
    diagCard() {
        const rows = (this.data.discharge_diags || []).map((d, i) => `<tr>
            <td>${Format.escape(d.name || '')}</td>
            <td>${Format.escape(d.code || '')}</td>
            <td>${this.selectHtml('data-cf-diag="' + i + '" data-field="in_status"', CaseHomeFields.diagInStatus, d.in_status)}</td>
            <td>${this.selectHtml('data-cf-diag="' + i + '" data-field="outcome"', CaseHomeFields.diagOutcome, d.outcome)}</td>
        </tr>`).join('') || '<tr><td colspan="4"><div class="empty-state">暂无出院诊断（请在诊断模块维护出院诊断后刷新）</div></td></tr>';
        return `<div class="card"><div class="card-header">出院诊断<span class="fs-12 text-muted">诊断与编码自动取自诊断模块；仅入院病情/治疗转归可补充</span></div>
            <table class="table"><thead><tr><th>出院诊断</th><th>疾病编码</th><th>入院病情</th><th>治疗转归</th></tr></thead>
            <tbody>${rows}</tbody></table></div>`;
    },

    selectHtml(attrs, opts, v) {
        return `<select class="form-control" ${attrs}>${opts.map(o =>
            `<option value="${o[0]}" ${String(v || '') === String(o[0]) ? 'selected' : ''}>${Format.escape(this.optText(o[1]))}</option>`).join('')}</select>`;
    },

    /* 手术: 自动取自手术管理 */
    surgeryCard() {
        const rows = (this.data.surgeries || []).map(s => `<tr>
            <td>${Format.escape(s.date || '')}</td><td>${Format.escape(s.name || '')}</td>
            <td>${Format.escape(String(s.surgeon || ''))}</td><td>${Format.escape(s.anesthesia || '')}</td></tr>`).join('')
            || '<tr><td colspan="4"><div class="empty-state">暂无手术记录（自动取自手术管理）</div></td></tr>';
        return `<div class="card"><div class="card-header">手术及操作<span class="fs-12 text-muted">自动取自手术管理，无需手工填写</span></div>
            <table class="table"><thead><tr><th>手术日期</th><th>手术及操作名称</th><th>术者</th><th>麻醉方式</th></tr></thead>
            <tbody>${rows}</tbody></table></div>`;
    },

    money(k) {
        const n = Number(this.raw(k) || 0);
        return n.toFixed(2);
    },

    /* 住院费用: 后台按医嘱自动归集, 页面只读 */
    feeCard() {
        const rows = CaseHomeFields.feeItems.map(it => {
            const sub = (it.sub || []).map(s => `${s[1]} ${this.money(s[0])}`).join('　');
            return `<tr><td>${it.l}</td><td class="tar cf-fee-num">${this.money(it.k)}</td><td class="cf-fee-sub">${sub}</td></tr>`;
        }).join('');
        return `<div class="card"><div class="card-header">住院费用（元）<span class="fs-12 text-muted">由系统按有效医嘱自动计算，无需手工填写</span></div>
            <div class="cf-grid">
                <div class="form-group"><label class="form-label">住院费用总计</label><input readonly class="form-control cf-readonly" value="${this.money('fee_total')}"></div>
                <div class="form-group"><label class="form-label">自付金额</label><input readonly class="form-control cf-readonly" value="${this.money('fee_self')}"></div>
            </div>
            <table class="table cf-fee-table"><tbody>${rows}</tbody></table></div>`;
    },

    icuCard() {
        const rows = (this.data.icu || []).map((r, i) => `<tr>
            <td><input class="form-control" data-cf-icu="${i}" data-field="name" value="${Format.escape(r.name || '')}"></td>
            <td><input class="form-control" data-cf-icu="${i}" data-field="in" data-dtpicker="datetime" value="${Format.escape(r.in || '')}"></td>
            <td><input class="form-control" data-cf-icu="${i}" data-field="out" data-dtpicker="datetime" value="${Format.escape(r.out || '')}"></td>
            <td style="width:56px"><button class="btn btn-small" onclick="CaseHome.removeIcu(${i})">删除</button></td>
        </tr>`).join('');
        return `<div class="card"><div class="card-header">重症监护室
            <button class="btn btn-small" style="float:right" onclick="CaseHome.addIcu()">添加</button></div>
            <table class="table"><thead><tr><th>重症监护室名称</th><th>进入时间</th><th>转出时间</th><th></th></tr></thead>
            <tbody>${rows}</tbody></table></div>`;
    },

    addIcu() {
        this.collect();
        this.data.icu = (this.data.icu || []).concat([{ name: '', in: '', out: '' }]);
        this.render();
    },

    removeIcu(i) {
        this.collect();
        this.data.icu.splice(i, 1);
        if (!this.data.icu.length) this.data.icu = [{ name: '', in: '', out: '' }];
        this.render();
    },

    collect() {
        document.querySelectorAll('[data-cf]').forEach(el => { this.data[el.dataset.cf] = el.value; });
        document.querySelectorAll('[data-cf-diag]').forEach(el => {
            const d = (this.data.discharge_diags || [])[Number(el.dataset.cfDiag)];
            if (d) d[el.dataset.field] = el.value;
        });
        document.querySelectorAll('[data-cf-icu]').forEach(el => {
            const i = Number(el.dataset.cfIcu);
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
};
