<?php header("Content-Type: text/html; charset=utf-8"); ?>
<div class="page-head flex-between">
    <div>
        <h3 class="page-title">处方</h3>
        <p class="page-desc">开具处方（药品项目库来自门诊）与已开处方管理</p>
    </div>
    <div>
        <button class="btn btn-primary" onclick="RxPanel.open()">新方（F9）</button>
        <button class="btn" onclick="clinic_rx_load()">刷新</button>
    </div>
</div>

<div class="card">
    <div class="card-header">已开处方 <span class="fs-12 text-muted">编辑请前往医嘱工作站；参考金额按项目库价格估算</span></div>
    <div id="rx-notice"></div>
    <div style="overflow-x:auto">
    <table class="table" id="rx-table">
        <thead><tr><th>次</th><th>处方</th><th>项数</th><th>状态</th><th>开单时间</th><th>参考金额</th><th>操作</th></tr></thead>
        <tbody id="rx-body"></tbody>
    </table>
    </div>
</div>

<script>
var _rxPriceMap = {};
var _rxSource = 'local';
var _rxGroups = [];

async function clinic_rx_init() {
    await App.mountShell('doctor');
    clinic_rx_load();
    document.addEventListener('keydown', function(e) {
        if (e.key === 'F9' && window.location.hash === '#clinic/rx') {
            e.preventDefault();
            RxPanel.open();
        }
    });
}

/* 处方整体状态: 有一个待核对即待核对, 依次降级 */
function _rxStatus(items) {
    const has = s => items.some(i => i.status === s);
    if (has('pending')) return 'pending';
    if (has('invalid_pending')) return 'invalid_pending';
    if (has('stop_pending')) return 'stop_pending';
    if (has('verified')) return 'verified';
    if (has('executing')) return 'executing';
    if (has('completed')) return 'completed';
    if (has('stopped')) return 'stopped';
    return 'invalid';
}

function clinic_rx_load() {
    const p = PatientContext.current();
    if (!p) return;
    Promise.all([
        AjaxLoader.api('orders/rx-list?patient_id=' + p.id, { silent: true }),
        AjaxLoader.api('rx/catalog', { silent: true })
    ]).then(([or, cr]) => {
        _rxSource = cr.source || 'local';
        _rxPriceMap = {};
        (cr.data || []).forEach(d => { _rxPriceMap[d.name] = Number(d.price || 0); });
        // 按处方号归集 (历史无处方号的行按开方时间归集)
        const map = {};
        (or.data || []).forEach(o => {
            const key = o.rx_no || ('L' + (o.created_at || '') + '|' + (o.start_date || ''));
            if (!map[key]) map[key] = { rx_no: o.rx_no || '', created_at: o.created_at || o.start_date || '', pharmacy_type: o.pharmacy_type || 'center', pharmacy_name: o.pharmacy_name || '中心药房', print_snapshot: o.print_snapshot || null, items: [] };
            map[key].items.push(o);
        });
        _rxGroups = Object.keys(map).map(k => map[k]);
        const notice = document.getElementById('rx-notice');
        if (!_rxGroups.length) {
            notice.innerHTML = '<div class="empty-state">暂无处方，点击右上角「新方」开具</div>';
            document.getElementById('rx-body').innerHTML = '';
            return;
        }
        notice.innerHTML = '';
        document.getElementById('rx-body').innerHTML = _rxGroups.map((g, i) => {
            const n = g.items.length;
            const title = n > 1 ? (g.items[0].content + ' 等 ' + n + ' 项') : g.items[0].content;
            let total = 0;
            g.items.forEach(it => { total += (_rxPriceMap[it.content] || 0); });
            const st = _rxStatus(g.items);
            const canVoid = g.items.some(it => ['pending', 'verified', 'executing'].includes(it.status));
            const drafts = g.items.filter(it => it.status === 'draft');
            const acts = [`<button class="btn btn-small" onclick="clinic_rx_print(${i})">打印</button>`];
            if (canVoid) acts.push(`<button class="btn btn-small btn-danger" onclick="clinic_rx_void(${i})">毁方</button>`);
            if (drafts.length) acts.push(`<button class="btn btn-small" onclick="clinic_rx_delete(${i})">删除</button>`);
            return `<tr>
                <td>${i + 1}</td>
                <td>${Format.escape(title)}</td>
                <td>${n}</td>
                <td><span class="badge ${Format.orderStatusClass(st)}">${Format.orderStatus(st)}</span></td>
                <td>${Format.escape(g.created_at)}</td>
                <td>¥${total.toFixed(2)}</td>
                <td>${acts.join(' ')}</td>
            </tr>`;
        }).join('');
    }).catch(() => {});
}

function clinic_rx_print(idx) {
    const g = _rxGroups[idx];
    const currentPatient = PatientContext.current();
    if (!g || !currentPatient) return;
    const frozen = PrintSnapshot.exists(g.print_snapshot) ? PrintSnapshot.data(g.print_snapshot, null) : null;
    const p = frozen ? (frozen.patient || {}) : currentPatient;
    const items = frozen ? (frozen.items || []) : g.items;
    let total = 0;
    if (frozen) total = Number(frozen.total_amount || 0);
    else items.forEach((it) => { total += Number(_rxPriceMap[it.content] || 0); });
    const rxNo = (frozen && frozen.rx_no) || g.rx_no || '处方草稿';
    // 门诊标准处方样式：℞ 标记 + 无边框药品表（名称/剂量/途径/频次/数量）
    const nameTxt = (it) => {
        let s = Format.escape(it.content || '');
        if (it.spec) s += ' ' + Format.escape(it.spec);
        if (it.vendor_short) s += ' ' + Format.escape(it.vendor_short);
        return s;
    };
    const unitOf = (it) => it.unit || '盒';
    const rxRows = items.map((it) => {
        const dose = [it.dosage || '', it.dosage_unit || ''].join('');
        return `<tr>
            <td class="rx-name">${nameTxt(it)}</td>
            <td class="rx-dose">${Format.escape(dose)}</td>
            <td class="rx-route">${Format.escape(it.usage_name || '')}</td>
            <td class="rx-freq">${Format.escape(it.freq_code || '')}</td>
            <td class="rx-qty">×${it.quantity || 1} ${Format.escape(unitOf(it))}</td>
        </tr>`;
    }).join('');
    const doctorName = (frozen && frozen.doctor_name) || (items[0] || {}).creator_name || '';
    // 参照门诊标准处方笺：顶部医院名 + 右上条码 + 文书名，随后患者信息（姓名/性别/出生日期/年龄、
    // 患者ID/住院号/床号/科室 + 临床诊断），℞ 标记 + 药品表，底部取药提示/金额/签名/调配/时间。
    const age = p.age_snapshot || p.age || (p.birth_date ? Format.ageClinical(p.birth_date) : '');
    const diag = p.admission_diagnosis || '';
    const html = `<div class="sheet a5">
        <div class="rx-head">
            <div class="rx-brand">${PrintHelper.brandHeaderHtml('', frozen || p)}</div>
            <div class="rx-barcode">${PrintBarcode.svg(rxNo)}</div>
        </div>
        <div class="rx-title">住院处方笺</div>
        <div class="rx-info">
            <div class="rx-info-row">
                <span>姓名：<b>${Format.escape(p.name || '')}</b></span>
                <span>性别：<b>${Format.gender(p.gender)}</b></span>
                <span>出生日期：<b>${Format.escape((p.birth_date || '').slice(0, 10) || '')}</b></span>
                <span>年龄：<b>${Format.escape(age)}</b></span>
            </div>
            <div class="rx-info-row">
                <span>患者ID：<b>${Format.escape(p.patient_no || p.id || '')}</b></span>
                <span>住院号：<b>${Format.escape(p.admission_no || '')}</b></span>
                <span>床号：<b>${Format.escape(p.bed_no || '-')}</b></span>
                <span>科室：<b>${Format.escape(p.department_name || '')}</b></span>
            </div>
            <div class="rx-info-row">${diag ? `<span class="rx-diag">临床诊断：<b>${Format.escape(diag)}</b></span>` : ''}</div>
        </div>
        <div class="rx-rule"></div>
        <div class="rx-mark">℞</div>
        <table class="rx-print">
            ${rxRows}
        </table>
        <div class="rx-end">—————— 处方完毕 ——————</div>
        <div class="rx-foot">
            <div class="rx-take">请凭本处方单至${Format.escape((frozen && frozen.pharmacy_name) || g.pharmacy_name || '药房')}取药</div>
            <div class="rx-amount-row"><span>金额：¥${total.toFixed(2)}</span><span class="rx-doctor">医师签名：${Format.escape(doctorName)}</span></div>
            <div class="rx-line"></div>
            <div class="rx-disp-row"><span>调配：</span><span>复核、发药：</span></div>
            <div class="rx-line"></div>
            <div class="rx-time-row"><span>开单时间：${Format.escape((frozen && frozen.created_time_text) || (frozen && frozen.created_at) || g.created_at || '')}</span><span>打印时间：${Format.datetime(new Date().toISOString())}</span></div>
            <div class="rx-valid">（本处方当日内有效）</div>
        </div>
    </div>`;
    PrintPreview.openBody('住院处方笺', html);
}

/* 毁方: 整方作废 (护士未核对也可毁方), 作废后同步护士站 */
function clinic_rx_void(idx) {
    const g = _rxGroups[idx];
    if (!g) return;
    Dom.modal('毁方确认', '<p>确认毁方？该处方（' + g.items.length + ' 项）将整方作废，作废记录同步护士站。</p>', [
        { text: '毁方', type: 'danger', onclick: async function() {
            const data = g.rx_no ? { rx_no: g.rx_no } : { ids: g.items.map(it => it.id) };
            const r = await AjaxLoader.api('orders/rx-void', { method: 'POST', data: data });
            if (r.status === 'success') {
                Dom.toast('已毁方，医嘱已作废', 'success');
                clinic_rx_load();
            }
        } },
        { text: '取消', type: 'default' }
    ]);
}

function clinic_rx_delete(idx) {
    const g = _rxGroups[idx];
    if (!g) return;
    const ids = g.items.filter(it => it.status === 'draft').map(it => it.id);
    if (!ids.length) return;
    Dom.confirm('删除处方', '删除该处方草稿医嘱？', async function() {
            await AjaxLoader.api('orders/batch-delete', { method: 'POST', data: { ids: ids } });
            Dom.toast('已删除', 'success');
            clinic_rx_load();
        }, { danger: true, okText: '删除' });
}

/* ===== 开方面板 ===== */
</script>
<?php /* 处方开具面板 RxPanel (拆分自本文件) */ ?>
<?php include __DIR__ . '/rx/panel.php'; ?>
